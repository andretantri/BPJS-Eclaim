<?php

namespace App\Http\Controllers;

use App\Enums\ClaimStatus;
use App\Enums\RiskFollowUpStatus;
use App\Enums\StatusVerifikasi;
use App\Enums\TahapVerifikasi;
use App\Events\ClaimStatusChanged;
use App\Http\Requests\UploadDocumentRequest;
use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\ClaimDocument;
use App\Models\ClaimRisk;
use App\Services\BpjsVclaimService;
use App\Services\ClaimVerificationService;
use App\Services\IcdValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function __construct(
        protected BpjsVclaimService $vclaimService,
        protected ClaimVerificationService $verificationService,
        protected IcdValidationService $icdValidationService,
    ) {}

    public function selectPatient(Request $request): Response
    {
        Gate::authorize('viewAny', Claim::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'step' => ['nullable', 'string', 'max:50'],
        ]);

        $stage = TahapVerifikasi::fromStep($filters['step'] ?? null) ?? TahapVerifikasi::Vclaim;
        $search = trim((string) ($filters['q'] ?? ''));

        $claims = Claim::query()
            ->forHospital($request->user()?->hospital_id)
            ->with('patient:id,no_rm,nama_lengkap')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('no_sep', 'like', "%{$search}%")
                        ->orWhere('nomor_klaim', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($query) use ($search): void {
                            $query->where('nama_lengkap', 'like', "%{$search}%")
                                ->orWhere('no_rm', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Verification/SelectPatient', [
            'claims' => $claims,
            'filters' => ['q' => $search],
            'selectedStep' => $stage->step(),
            'selectedStepLabel' => $stage->label(),
        ]);
    }

    public function show(Request $request, ?Claim $claim = null, string $step = 'v-claim'): Response|RedirectResponse
    {
        if ($claim === null) {
            $claim = Claim::query()
                ->forHospital($request->user()?->hospital_id)
                ->whereIn('status', [ClaimStatus::MenungguVerifikasi->value, ClaimStatus::Bermasalah->value])
                ->oldest('id')
                ->first()
                ?? Claim::query()
                    ->forHospital($request->user()?->hospital_id)
                    ->latest('id')
                    ->first();

            if ($claim === null) {
                return redirect()->route('klaim.index')->with('info', 'Belum ada klaim yang dapat diverifikasi.');
            }
        }

        $claim = $this->scopedClaim($request, $claim);

        Gate::authorize('view', $claim);

        $stage = TahapVerifikasi::fromStep($step) ?? TahapVerifikasi::Vclaim;
        $step = $stage->step();
        $unlockedIndex = $this->verificationService->highestUnlockedStageIndex($claim);
        $stages = $this->verificationService->orderedStages();
        $requestedIndex = array_search($stage, $stages, true);

        if ($requestedIndex !== false && $requestedIndex > $unlockedIndex) {
            $unlockedStage = $stages[$unlockedIndex];

            return redirect()->route('verifikasi.show', [
                'claim' => $claim->id,
                'step' => $unlockedStage->step(),
            ])->with('info', "Selesaikan tahap {$unlockedStage->label()} terlebih dahulu sebelum membuka tahap berikutnya.");
        }

        $stepMeta = [
            'v-claim' => [
                'title' => 'BPJS V-Claim',
                'sub' => 'VERIFIKASI KEPESERTAAN',
                'desc' => 'Telusuri SEP dan verifikasi kepesertaan. Jika kredensial BPJS belum tersedia, hasilnya adalah simulasi lokal.',
                'order' => 1,
            ],
            'pra-klaim' => [
                'title' => 'Verifikasi pra-klaim',
                'sub' => 'VALIDASI ADMINISTRASI',
                'desc' => 'Pastikan kelengkapan administrasi dan keabsahan dokumen sebelum pengajuan.',
                'order' => 2,
            ],
            'icd-10' => [
                'title' => 'Validasi ICD-10',
                'sub' => 'VALIDASI KODE DIAGNOSIS',
                'desc' => 'Tinjau kode diagnosis primer dan sekunder terhadap referensi ICD-10 aktif.',
                'order' => 3,
            ],
            'icd-9' => [
                'title' => 'Validasi ICD-9-CM',
                'sub' => 'VALIDASI KODE PROSEDUR',
                'desc' => 'Cocokkan kode prosedur dengan referensi ICD-9-CM aktif dan dokumentasi tindakan.',
                'order' => 4,
            ],
            'risiko-klaim' => [
                'title' => 'Deteksi risiko klaim',
                'sub' => 'PENAPISAN RISIKO & SENGKETA',
                'desc' => 'Identifikasi potensi sengketa, tetapkan tindak lanjut, dan simpan keputusan verifikasi.',
                'order' => 5,
            ],
            'tinjauan-akhir' => [
                'title' => 'Tinjauan akhir',
                'sub' => 'RINGKASAN KESIAPAN KLAIM',
                'desc' => 'Tinjau hasil validasi QA internal rumah sakit sebelum klaim ditandai siap diajukan.',
                'order' => 6,
            ],
        ];

        $vclaimResult = $this->vclaimService->searchSep($claim->no_sep);

        return Inertia::render('Verification/Show', [
            'claim' => $claim,
            'currentStep' => $step,
            'stepMeta' => array_merge($stepMeta[$step], ['stage' => $stage->value]),
            'vclaimResult' => $vclaimResult,
            'steps' => collect($stages)->map(fn (TahapVerifikasi $item): string => $item->step())->all(),
            'unlockedSteps' => collect($stages)
                ->take($unlockedIndex + 1)
                ->map(fn (TahapVerifikasi $item): string => $item->step())
                ->all(),
            'diagnoses' => $claim->diagnoses,
            'procedures' => $claim->procedures,
            'isReadyForSubmission' => $this->verificationService->isReadyForSubmission($claim),
        ]);
    }

    public function checkSep(Request $request, Claim $claim): RedirectResponse
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('verify', $claim);

        $validated = $request->validate([
            'sep' => ['required', 'string', 'max:50', Rule::in([$claim->no_sep])],
        ]);

        $result = $this->vclaimService->searchSep($validated['sep']);
        $code = (string) data_get($result, 'metaData.code', '');

        if ($code !== '200') {
            $this->verificationService->recordVclaimVerification($claim, $result, $request->user());

            return redirect()->back()->with('error', 'Nomor SEP tidak ditemukan pada V-Claim BPJS.');
        }

        $this->verificationService->recordVclaimVerification($claim, $result, $request->user());

        $message = data_get($result, 'simulated', false)
            ? 'Pencarian SEP cocok dengan data simulasi lokal (bukan konfirmasi BPJS sungguhan).'
            : 'Nomor SEP valid dan terkonfirmasi pada V-Claim BPJS.';

        return redirect()->back()->with(
            data_get($result, 'simulated', false) ? 'info' : 'success',
            $message,
        );
    }

    public function uploadResume(UploadDocumentRequest $request, Claim $claim): RedirectResponse
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('verify', $claim);

        $file = $request->file('dokumen');
        $path = $file->store("claim-documents/{$claim->hospital_id}/{$claim->id}", 'local');

        $claim = $this->verificationService->resolveDpjpResume($claim, $request->user(), [
            'nama_file' => $file->getClientOriginalName(),
            'path_file' => $path,
            'ukuran_file' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'catatan' => $request->input('catatan'),
        ]);

        $message = 'Revisi resume medis bertanda tangan berhasil disimpan.';
        if ($claim->status === ClaimStatus::SiapDiajukan) {
            $message .= ' Status klaim menjadi '.ClaimStatus::SiapDiajukan->label().'.';
        } else {
            $message .= ' Klaim masih menunggu tahap verifikasi lainnya.';
        }

        return redirect()->back()->with('success', $message);
    }

    public function downloadDocument(Request $request, Claim $claim, ClaimDocument $document): StreamedResponse
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('view', $claim);

        abort_unless(
            $document->claim_id === $claim->id
                && filled($document->path_file)
                && Storage::disk('local')->exists($document->path_file),
            404,
        );

        return Storage::disk('local')->download($document->path_file, $document->nama_file);
    }

    public function validateIcd10(Request $request, Claim $claim): RedirectResponse
    {
        return $this->recordValidation($request, $claim, TahapVerifikasi::Icd10, 'ICD-10');
    }

    public function validateIcd9(Request $request, Claim $claim): RedirectResponse
    {
        return $this->recordValidation($request, $claim, TahapVerifikasi::Icd9cm, 'ICD-9-CM');
    }

    public function evaluateRisks(Request $request, Claim $claim): RedirectResponse
    {
        return $this->recordValidation($request, $claim, TahapVerifikasi::Risiko, 'risiko klaim');
    }

    public function resolveRisk(Request $request, Claim $claim, ClaimRisk $risk): RedirectResponse
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('verify', $claim);

        abort_unless($risk->claim_id === $claim->id, 404);

        if (! $this->verificationService->isStageUnlocked($claim, TahapVerifikasi::Risiko)) {
            return redirect()->back()->with('error', 'Tahap sebelumnya belum selesai; temuan risiko belum dapat ditindaklanjuti.');
        }

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    RiskFollowUpStatus::Terselesaikan->value,
                    RiskFollowUpStatus::DiterimaRisiko->value,
                ]),
            ],
            'catatan' => ['required', 'string', 'max:500'],
        ]);

        $this->verificationService->resolveRisk(
            $claim,
            $risk,
            RiskFollowUpStatus::from($validated['status']),
            $validated['catatan'],
            $request->user(),
        );

        return redirect()->back()->with('success', 'Tindak lanjut temuan risiko berhasil disimpan.');
    }

    public function markReady(Request $request, Claim $claim): RedirectResponse
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('approve', $claim);

        if (! $this->verificationService->isReadyForSubmission($claim)) {
            return redirect()->back()->with('error', 'Klaim belum memenuhi seluruh tahap verifikasi dan tidak dapat ditandai siap diajukan.');
        }

        $this->verificationService->markReadyForSubmission($claim, $request->user());

        return redirect()->back()->with(
            'success',
            'Klaim berhasil ditandai siap diajukan. Kirim melalui portal E-Claim untuk membuat batch pengiriman.',
        );
    }

    public function returnForRevision(Request $request, Claim $claim): RedirectResponse
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('verify', $claim);

        $validated = $request->validate([
            'catatan' => ['required', 'string', 'max:500'],
        ]);

        $previousStatus = $claim->status;

        $claim->update(['status' => ClaimStatus::Bermasalah]);

        $claim->verifications()->where('tahap', TahapVerifikasi::TinjauanAkhir->value)->update([
            'status' => StatusVerifikasi::PerluRevisi,
            'catatan' => $validated['catatan'],
            'verified_by' => $request->user()?->id,
            'verified_at' => now(),
        ]);

        event(new ClaimStatusChanged($claim, $previousStatus, $request->user()));

        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'claim_id' => $claim->id,
            'action' => 'Kembalikan untuk revisi',
            'description' => "Berkas klaim {$claim->nomor_klaim} dikembalikan untuk perbaikan: {$validated['catatan']}",
        ]);

        return redirect()->back()->with('info', 'Berkas klaim dikembalikan ke unit rawat inap / DPJP untuk perbaikan.');
    }

    /**
     * @return array{status: StatusVerifikasi, catatan: string, detail: array<string, mixed>}
     */
    private function validationResultFor(TahapVerifikasi $stage, Claim $claim): array
    {
        return match ($stage) {
            TahapVerifikasi::Icd10 => $this->icdValidationService->validateDiagnoses($claim),
            TahapVerifikasi::Icd9cm => $this->icdValidationService->validateProcedures($claim),
            TahapVerifikasi::Risiko => $this->icdValidationService->evaluateRisks($claim),
            default => throw new \InvalidArgumentException("Tahap {$stage->value} bukan tahap validasi ICD/risiko."),
        };
    }

    private function recordValidation(Request $request, Claim $claim, TahapVerifikasi $stage, string $label): RedirectResponse
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('verify', $claim);

        if (! $this->verificationService->isStageUnlocked($claim, $stage)) {
            return redirect()->back()->with('error', 'Selesaikan tahap sebelumnya sebelum menjalankan validasi ini.');
        }

        $result = $this->validationResultFor($stage, $claim);

        $this->verificationService->recordStageResult($claim, $stage, $result, $request->user());

        return redirect()->back()->with(
            $result['status'] === StatusVerifikasi::Lolos ? 'success' : 'error',
            "Validasi {$label}: {$result['catatan']}",
        );
    }

    private function scopedClaim(Request $request, Claim $claim): Claim
    {
        return Claim::with([
            'patient',
            'documents',
            'verifications',
            'risks',
            'diagnoses.icd10Code',
            'procedures.icd9cmCode',
        ])
            ->forHospital($request->user()?->hospital_id)
            ->findOrFail($claim->id);
    }
}
