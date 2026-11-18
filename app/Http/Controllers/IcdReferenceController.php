<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIcdCodeRequest;
use App\Models\Icd10Code;
use App\Models\Icd9cmCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IcdReferenceController extends Controller
{
    public function index(Request $request, string $type): Response
    {
        $modelClass = $this->modelClass($type);
        Gate::authorize('viewAny', $modelClass);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $normalizedSearch = strtoupper($search);

        $codes = $modelClass::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('kode', 'like', "%{$search}%")
                        ->orWhere('deskripsi_id', 'like', "%{$search}%")
                        ->orWhere('deskripsi_en', 'like', "%{$search}%")
                        ->orWhere('kategori', 'like', "%{$search}%");
                });
            })
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        $exactMatchExists = $normalizedSearch !== ''
            && $modelClass::where('kode', $normalizedSearch)->exists();

        return Inertia::render('Admin/Icd/Index', [
            'type' => $type,
            'label' => $this->label($type),
            'codes' => $codes,
            'filters' => ['q' => $search],
            'exactMatchExists' => $exactMatchExists,
        ]);
    }

    public function create(Request $request, string $type): Response
    {
        $modelClass = $this->modelClass($type);
        Gate::authorize('create', $modelClass);

        $prefill = strtoupper(trim((string) $request->query('kode', '')));

        return Inertia::render('Admin/Icd/Create', [
            'type' => $type,
            'label' => $this->label($type),
            'prefillKode' => substr($prefill, 0, 20),
        ]);
    }

    public function store(StoreIcdCodeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $type = (string) $validated['type'];
        unset($validated['type']);

        $modelClass = $this->modelClass($type);
        $modelClass::create($validated);

        return redirect()->route('admin.icd.index', ['type' => $type])
            ->with('success', "Kode {$validated['kode']} berhasil ditambahkan ke referensi {$this->label($type)}.");
    }

    public function toggle(Request $request, string $type, int $icdCode): RedirectResponse
    {
        $modelClass = $this->modelClass($type);
        Gate::authorize('update', $modelClass);

        $validated = $request->validate([
            'is_active' => ['boolean'],
        ]);

        $code = $modelClass::findOrFail($icdCode);
        $code->update(['is_active' => $validated['is_active']]);

        return redirect()->back()->with(
            'success',
            "Kode {$code->kode} ".($code->is_active ? 'diaktifkan' : 'dinonaktifkan').'.',
        );
    }

    /**
     * @return class-string<Icd10Code>|class-string<Icd9cmCode>
     */
    private function modelClass(string $type): string
    {
        abort_unless(in_array($type, ['icd10', 'icd9cm'], true), 404);

        return $type === 'icd9cm' ? Icd9cmCode::class : Icd10Code::class;
    }

    private function label(string $type): string
    {
        return $type === 'icd9cm' ? 'ICD-9-CM' : 'ICD-10';
    }
}
