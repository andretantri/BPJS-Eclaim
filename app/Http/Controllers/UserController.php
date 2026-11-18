<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $roleFilter = trim((string) ($filters['role'] ?? ''));

        $users = User::query()
            ->with([
                'role:id,name,display_name',
                'hospital:id,nama_rs',
            ])
            ->where('hospital_id', $request->user()?->hospital_id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter !== '', function ($query) use ($roleFilter): void {
                $query->whereHas('role', fn ($query) => $query->where('name', $roleFilter));
            })
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('id')->get(['id', 'name', 'display_name']),
            'hospitals' => Hospital::where('id', $request->user()?->hospital_id)->get(['id', 'nama_rs']),
            'filters' => ['q' => $search, 'role' => $roleFilter],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::orderBy('id')->get(['id', 'name', 'display_name', 'permissions']),
            'hospitals' => Hospital::where('id', $request->user()?->hospital_id)->get(['id', 'nama_rs']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['hospital_id'] = $request->user()?->hospital_id;

        $user = User::create($validated);

        return redirect()->route('admin.users.index')
            ->with('success', "Akun {$user->name} berhasil ditambahkan.");
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('Admin/Users/Edit', [
            'targetUser' => $user->loadMissing('role'),
            'roles' => Role::orderBy('id')->get(['id', 'name', 'display_name', 'permissions']),
            'hospitals' => Hospital::where('id', $request->user()?->hospital_id)->get(['id', 'nama_rs']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if ($user->is($request->user())) {
            if ((int) $validated['role_id'] !== (int) $user->role_id) {
                return redirect()->back()->with('warning', 'Role akun sendiri tidak dapat diubah.');
            }

            if (array_key_exists('is_active', $validated) && ! $validated['is_active']) {
                return redirect()->back()->with('warning', 'Akun sendiri tidak dapat dinonaktifkan.');
            }
        }

        if (empty($validated['password'])) {
            unset($validated['password'], $validated['password_confirmation']);
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')
            ->with('success', "Akun {$user->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        if ($user->is($request->user())) {
            return redirect()->back()->with('warning', 'Akun sendiri tidak dapat dihapus.');
        }

        $user->loadMissing('role');

        if ($user->isAdmin()) {
            $activeAdminCount = User::where('hospital_id', $user->hospital_id)
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'admin_casemix'))
                ->count();

            if ($activeAdminCount <= 1) {
                return redirect()->back()->with('warning', 'Administrator aktif terakhir tidak dapat dihapus.');
            }
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "Akun {$name} berhasil dihapus.");
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('impersonate', $user);

        if ($request->session()->has('impersonator_id')) {
            abort(403, 'Kembali ke akun administrator sebelum beralih ke akun lain.');
        }

        $request->session()->put('impersonator_id', $request->user()?->id);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('info', "Anda sedang melihat aplikasi sebagai {$user->name}.");
    }

    public function leaveImpersonation(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if ($impersonatorId === null) {
            abort(403, 'Tidak ada sesi impersonasi yang aktif.');
        }

        $administrator = User::with(['role', 'hospital'])->findOrFail($impersonatorId);

        Auth::login($administrator);
        $request->session()->forget('impersonator_id');
        $request->session()->regenerate();

        return redirect()->route('admin.users.index')
            ->with('success', "Kembali ke akun {$administrator->name}.");
    }
}
