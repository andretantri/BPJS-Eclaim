<?php

namespace App\Http\Controllers;

use App\Enums\EclaimItemStatus;
use App\Http\Requests\SyncEclaimRequest;
use App\Jobs\SyncEclaimBatch;
use App\Models\Claim;
use App\Models\EclaimSync;
use App\Services\BpjsEclaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EclaimController extends Controller
{
    public function __construct(
        protected BpjsEclaimService $eclaimService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', EclaimSync::class);

        $hospitalId = $request->user()?->hospital_id;

        $readyClaims = Claim::with('patient')
            ->forHospital($hospitalId)
            ->readyToSubmit()
            ->orderBy('id')
            ->get();

        $syncHistory = EclaimSync::with(['initiator', 'items.claim.patient'])
            ->where('hospital_id', $hospitalId)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        return Inertia::render('Eclaim/Index', [
            'readyClaims' => $readyClaims,
            'syncHistory' => $syncHistory,
            'stats' => [
                'totalSynced' => Claim::forHospital($hospitalId)->where('is_synced_eclaim', true)->count(),
                'totalReadyToSync' => $readyClaims->count(),
                'totalBatch' => EclaimSync::where('hospital_id', $hospitalId)->count(),
                'mode' => $this->eclaimService->isConfigured() ? 'bpjs' : 'simulasi_lokal',
            ],
        ]);
    }

    public function syncBatch(SyncEclaimRequest $request): RedirectResponse
    {
        Gate::authorize('sync', EclaimSync::class);

        $claimIds = $request->validated()['claim_ids'] ?? null;

        if ($claimIds === null) {
            $claimIds = Claim::forHospital($request->user()?->hospital_id)
                ->readyToSubmit()
                ->pluck('id')
                ->all();
        } else {
            $claimIds = Claim::forHospital($request->user()?->hospital_id)
                ->whereIn('id', $claimIds)
                ->readyToSubmit()
                ->pluck('id')
                ->all();
        }

        if ($claimIds === []) {
            return redirect()->back()->with('warning', 'Tidak ada klaim siap diajukan untuk disinkronisasi.');
        }

        $sync = $this->eclaimService->createBatch($claimIds, $request->user()?->id);
        SyncEclaimBatch::dispatch($sync, $claimIds);

        $message = $sync->tipe_sync === 'simulasi_lokal'
            ? "Batch {$sync->batch_code} masuk antrean simulasi lokal: {$sync->total_items} klaim (belum dikirim ke BPJS)."
            : "Batch {$sync->batch_code} masuk antrean pengiriman E-Claim BPJS: {$sync->total_items} klaim.";

        return redirect()->back()->with($sync->tipe_sync === 'simulasi_lokal' ? 'info' : 'success', $message);
    }

    public function retryBatch(Request $request, EclaimSync $sync): RedirectResponse
    {
        $sync = EclaimSync::where('hospital_id', $request->user()?->hospital_id)
            ->findOrFail($sync->id);

        Gate::authorize('retry', $sync);

        $failedClaimIds = $sync->items()
            ->where('status', EclaimItemStatus::Failed->value)
            ->pluck('claim_id')
            ->all();

        if ($failedClaimIds === []) {
            return redirect()->back()->with('warning', 'Batch ini tidak memiliki klaim gagal untuk diulang.');
        }

        $claimIds = Claim::forHospital($request->user()?->hospital_id)
            ->whereIn('id', $failedClaimIds)
            ->readyToSubmit()
            ->pluck('id')
            ->all();

        if ($claimIds === []) {
            return redirect()->back()->with('warning', 'Klaim gagal tidak lagi memenuhi syarat untuk dikirim ulang.');
        }

        $retrySync = $this->eclaimService->createBatch($claimIds, $request->user()?->id);
        SyncEclaimBatch::dispatch($retrySync, $claimIds);

        return redirect()->back()->with(
            $retrySync->tipe_sync === 'simulasi_lokal' ? 'info' : 'success',
            "Retry batch {$retrySync->batch_code} masuk antrean untuk {$retrySync->total_items} klaim.",
        );
    }

    public function syncSingle(Request $request, Claim $claim): RedirectResponse
    {
        $claim = Claim::forHospital($request->user()?->hospital_id)
            ->findOrFail($claim->id);

        Gate::authorize('syncEclaim', $claim);

        if (! $claim->is_synced_eclaim && $claim->status?->value !== 'siap_diajukan') {
            return redirect()->back()->with('warning', 'Hanya klaim dengan status siap diajukan yang dapat dikirim.');
        }

        $result = $this->eclaimService->syncClaim($claim, $request->user());

        return redirect()->back()->with(
            data_get($result, 'simulated', false) ? 'info' : 'success',
            (string) data_get($result, 'message'),
        );
    }
}
