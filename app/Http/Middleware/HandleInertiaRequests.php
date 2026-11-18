<?php

namespace App\Http\Middleware;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\ClaimPeriod;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user()?->load(['role', 'hospital']);
        $hospitalId = $user?->hospital_id;
        $hasClaimsTable = Schema::hasTable('claims');

        $hospital = $user?->hospital;

        $currentPeriod = $hospitalId
            ? ClaimPeriod::where('hospital_id', $hospitalId)->where('status', 'aktif')->first()
            : null;

        $bpjsConfigured = filled(config('services.bpjs.cons_id'))
            && filled(config('services.bpjs.secret_key'))
            && filled(config('services.bpjs.user_key'));

        $impersonator = null;
        $impersonatorId = $request->session()->get('impersonator_id');

        if ($impersonatorId !== null) {
            $originalUser = User::with('role')->find($impersonatorId);

            if ($originalUser !== null) {
                $impersonator = [
                    'id' => $originalUser->id,
                    'name' => $originalUser->name,
                    'email' => $originalUser->email,
                ];
            }
        }

        $claimStats = [
            'total' => 0,
            'menunggu' => 0,
            'bermasalah' => 0,
            'siap' => 0,
        ];

        if ($hasClaimsTable) {
            $counts = Claim::forHospital($hospitalId)
                ->selectRaw(
                    'COUNT(*) as all_count,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as menunggu_count,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as bermasalah_count,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as siap_count',
                    [
                        ClaimStatus::MenungguVerifikasi->value,
                        ClaimStatus::Bermasalah->value,
                        ClaimStatus::SiapDiajukan->value,
                    ],
                )
                ->first();

            $claimStats = [
                'total' => (int) ($counts->all_count ?? 0),
                'menunggu' => (int) ($counts->menunggu_count ?? 0),
                'bermasalah' => (int) ($counts->bermasalah_count ?? 0),
                'siap' => (int) ($counts->siap_count ?? 0),
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user?->role?->permissions ?? [],
                'isAdmin' => $user?->isAdmin() ?? false,
            ],
            'hospital' => $hospital,
            'currentPeriod' => $currentPeriod,
            'bpjsConfigured' => $bpjsConfigured,
            'impersonator' => $impersonator,
            'claimStats' => $claimStats,
            'notifications' => [
                'unreadCount' => $user
                    ? Notification::where('user_id', $user->id)->where('is_read', false)->count()
                    : 0,
                'items' => $user
                    ? Notification::where('user_id', $user->id)->orderByDesc('created_at')->take(6)->get()->all()
                    : [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
