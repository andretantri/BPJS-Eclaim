<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\RiskFollowUpStatus;
use App\Enums\StatusVerifikasi;
use App\Enums\TahapVerifikasi;
use App\Enums\TingkatRisiko;
use App\Enums\TipeDokumen;
use App\Events\ClaimStatusChanged;
use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\ClaimRisk;
use App\Models\ClaimVerification;
use App\Models\User;

class ClaimVerificationService
{
    /**
     * Store the result of a V-Claim lookup on the verification record.
     *
     * @param  array<string, mixed>  $result
     */
    public function recordVclaimVerification(Claim $claim, array $result, ?User $user = null): ClaimVerification
    {
        $code = (string) data_get($result, 'metaData.code', '');
        $simulated = (bool) data_get($result, 'simulated', false);

        $catatan = $simulated
            ? 'Data SEP diambil dari database lokal (simulasi); kepesertaan BPJS belum dikonfirmasi.'
            : (string) data_get($result, 'metaData.message', 'Hasil pencarian SEP dari V-Claim BPJS.');

        return $claim->verifications()->updateOrCreate(
            ['tahap' => TahapVerifikasi::Vclaim->value],
            [
                'status' => $code === '200' ? StatusVerifikasi::Lolos : StatusVerifikasi::Gagal,
                'detail_hasil' => [
                    'metaData' => data_get($result, 'metaData'),
                    'response' => data_get($result, 'response'),
                    'simulated' => $simulated,
                ],
                'catatan' => $catatan,
                'verified_by' => $user?->id,
                'verified_at' => now(),
            ],
        );
    }

    /**
     * Resolve document blocker by uploading signed DPJP resume.
     *
     * @param  array{nama_file: string, path_file: string, ukuran_file: int, mime_type: string, catatan?: string|null}|null  $upload
     */
    public function resolveDpjpResume(Claim $claim, ?User $user = null, ?array $upload = null): Claim
    {
        if ($upload !== null) {
            $claim->documents()->updateOrCreate(
                ['tipe_dokumen' => TipeDokumen::ResumeMedis->value],
                [
                    'nama_file' => $upload['nama_file'],
                    'path_file' => $upload['path_file'],
                    'ukuran_file' => $upload['ukuran_file'],
                    'mime_type' => $upload['mime_type'],
                    'status_verifikasi' => StatusVerifikasi::Lengkap,
                    'catatan_verifikasi' => $upload['catatan'] ?: 'Tanda tangan DPJP terverifikasi lengkap via revisi unggahan digital.',
                    'verified_by' => $user?->id,
                    'verified_at' => now(),
                ],
            );
        }

        $resumeDoc = $claim->documents()->where('tipe_dokumen', TipeDokumen::ResumeMedis->value)->first();
        if ($resumeDoc && $upload === null) {
            $resumeDoc->update([
                'status_verifikasi' => StatusVerifikasi::Lengkap,
                'catatan_verifikasi' => 'Tanda tangan DPJP terverifikasi lengkap via revisi unggahan digital.',
                'verified_by' => $user?->id,
                'verified_at' => now(),
            ]);
        }

        $claim->risks()->where('kode_temuan', 'ADM-01')->update([
            'status_tindak_lanjut' => RiskFollowUpStatus::Terselesaikan,
            'resolved_by' => $user?->id,
            'resolved_at' => now(),
        ]);

        $previousStatus = $claim->status;
        $activeRisks = $claim->risks()
            ->whereNotIn('status_tindak_lanjut', [
                RiskFollowUpStatus::Terselesaikan->value,
                RiskFollowUpStatus::DiterimaRisiko->value,
            ])
            ->count();

        $claim->update([
            'doc_resume' => true,
            'issue' => 'Berkas lengkap',
            'issue_desc' => 'Resume telah ditandatangani oleh DPJP dan diverifikasi.',
            'jumlah_temuan_risiko' => $activeRisks,
            'tingkat_risiko' => $activeRisks === 0 ? TingkatRisiko::Rendah : $claim->tingkat_risiko,
        ]);

        $verificationData = [
            'status' => StatusVerifikasi::Lolos,
            'verified_by' => $user?->id,
            'verified_at' => now(),
        ];

        if ($upload !== null) {
            $verificationData['catatan'] = $upload['catatan'] ?? null;
            $verificationData['detail_hasil'] = [
                'nama_file' => $upload['nama_file'],
                'path_file' => $upload['path_file'],
            ];
        }

        $claim->verifications()->where('tahap', TahapVerifikasi::PraKlaim->value)->update($verificationData);

        $isReady = $this->isReadyForSubmission($claim);
        if ($isReady) {
            $claim->update([
                'status' => ClaimStatus::SiapDiajukan,
                'biaya_disetujui' => $claim->estimasi_biaya,
            ]);
            $claim->verifications()->where('tahap', TahapVerifikasi::TinjauanAkhir->value)->update([
                'status' => StatusVerifikasi::Lolos,
                'verified_by' => $user?->id,
                'verified_at' => now(),
            ]);
        }

        if ($claim->status !== $previousStatus) {
            event(new ClaimStatusChanged($claim, $previousStatus, $user));
        }

        ActivityLog::create([
            'user_id' => $user?->id,
            'claim_id' => $claim->id,
            'action' => 'Unggah revisi resume',
            'description' => "Revisi resume medis bertanda tangan diunggah untuk {$claim->nomor_klaim} ({$claim->patient->nama_lengkap}).".($isReady ? ' Klaim ditandai siap diajukan.' : ''),
        ]);

        return $claim->fresh(['patient', 'documents', 'verifications', 'risks']);
    }

    /**
     * Persist the result of one verification stage.
     *
     * @param  array{status: StatusVerifikasi, catatan: string, detail: array<string, mixed>}  $result
     */
    public function recordStageResult(Claim $claim, TahapVerifikasi $stage, array $result, ?User $user = null): ClaimVerification
    {
        return $claim->verifications()->updateOrCreate(
            ['tahap' => $stage->value],
            [
                'status' => $result['status'],
                'catatan' => $result['catatan'],
                'detail_hasil' => $result['detail'],
                'verified_by' => $user?->id,
                'verified_at' => now(),
            ],
        );
    }

    /**
     * @return array<int, TahapVerifikasi>
     */
    public function orderedStages(): array
    {
        return TahapVerifikasi::cases();
    }

    /**
     * Index of the furthest stage that may be opened; earlier stages must be complete first.
     */
    public function highestUnlockedStageIndex(Claim $claim): int
    {
        $statuses = $claim->verifications()->get(['tahap', 'status'])->keyBy('tahap');
        $completed = 0;

        foreach ($this->orderedStages() as $stage) {
            if ($stage === TahapVerifikasi::TinjauanAkhir) {
                break;
            }

            $status = $statuses->get($stage->value)?->status;

            if (! in_array($status, [StatusVerifikasi::Lolos, StatusVerifikasi::Lengkap], true)) {
                break;
            }

            $completed++;
        }

        return min($completed, count($this->orderedStages()) - 1);
    }

    public function isStageUnlocked(Claim $claim, TahapVerifikasi $stage): bool
    {
        $index = array_search($stage, $this->orderedStages(), true);

        return $index !== false && $index <= $this->highestUnlockedStageIndex($claim);
    }

    /**
     * Record a follow-up decision for a risk finding and refresh the claim risk counters.
     */
    public function resolveRisk(Claim $claim, ClaimRisk $risk, RiskFollowUpStatus $followUp, ?string $catatan, ?User $user = null): Claim
    {
        $claim->risks()->whereKey($risk->id)->update([
            'status_tindak_lanjut' => $followUp->value,
            'catatan_tindak_lanjut' => $catatan,
            'resolved_by' => $user?->id,
            'resolved_at' => now(),
        ]);

        $activeRisks = $claim->risks()
            ->whereNotIn('status_tindak_lanjut', [
                RiskFollowUpStatus::Terselesaikan->value,
                RiskFollowUpStatus::DiterimaRisiko->value,
            ])
            ->count();

        $claim->update([
            'jumlah_temuan_risiko' => $activeRisks,
            'tingkat_risiko' => $activeRisks === 0 ? TingkatRisiko::Rendah : $claim->tingkat_risiko,
        ]);

        ActivityLog::create([
            'user_id' => $user?->id,
            'claim_id' => $claim->id,
            'action' => 'Tindak lanjut risiko klaim',
            'description' => "Temuan {$risk->kode_temuan} pada {$claim->nomor_klaim} ditetapkan {$followUp->label()}.",
        ]);

        return $claim->fresh(['patient', 'documents', 'verifications', 'risks']);
    }

    /**
     * Determine whether every verification gate has been passed.
     */
    public function isReadyForSubmission(Claim $claim): bool
    {
        $documentTypes = array_map(
            fn (TipeDokumen $type): string => $type->value,
            TipeDokumen::cases(),
        );

        $completeDocuments = $claim->documents()
            ->whereIn('tipe_dokumen', $documentTypes)
            ->whereIn('status_verifikasi', [StatusVerifikasi::Lengkap->value, StatusVerifikasi::Lolos->value])
            ->count();

        if ($completeDocuments < count($documentTypes)) {
            return false;
        }

        $activeRisks = $claim->risks()
            ->whereNotIn('status_tindak_lanjut', [
                RiskFollowUpStatus::Terselesaikan->value,
                RiskFollowUpStatus::DiterimaRisiko->value,
            ])
            ->count();

        if ($activeRisks > 0) {
            return false;
        }

        foreach ([
            TahapVerifikasi::Vclaim,
            TahapVerifikasi::PraKlaim,
            TahapVerifikasi::Icd10,
            TahapVerifikasi::Icd9cm,
            TahapVerifikasi::Risiko,
        ] as $stage) {
            $verification = $claim->verifications()->where('tahap', $stage->value)->first();

            if (! in_array($verification?->status, [StatusVerifikasi::Lolos, StatusVerifikasi::Lengkap], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mark claim as ready for submission.
     */
    public function markReadyForSubmission(Claim $claim, ?User $user = null): Claim
    {
        $previousStatus = $claim->status;

        $claim->update([
            'status' => ClaimStatus::SiapDiajukan,
            'biaya_disetujui' => $claim->estimasi_biaya,
        ]);

        event(new ClaimStatusChanged($claim, $previousStatus, $user));

        $claim->verifications()->where('tahap', TahapVerifikasi::TinjauanAkhir->value)->update([
            'status' => StatusVerifikasi::Lolos,
            'verified_by' => $user?->id,
            'verified_at' => now(),
        ]);

        ActivityLog::create([
            'user_id' => $user?->id,
            'claim_id' => $claim->id,
            'action' => 'Klaim ditandai siap',
            'description' => "Klaim {$claim->nomor_klaim} disahkan dan siap diajukan ke BPJS.",
        ]);

        return $claim->fresh();
    }
}
