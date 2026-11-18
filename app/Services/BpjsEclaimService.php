<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\EclaimItemStatus;
use App\Enums\EclaimSyncStatus;
use App\Events\ClaimStatusChanged;
use App\Models\Claim;
use App\Models\EclaimSync;
use App\Models\EclaimSyncItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BpjsEclaimService
{
    public function isConfigured(): bool
    {
        return filled(config('services.bpjs.eclaim_url'))
            && filled(config('services.bpjs.cons_id'))
            && filled(config('services.bpjs.secret_key'))
            && filled(config('services.bpjs.user_key'));
    }

    /**
     * Submit one claim. Without credentials this is an explicit local simulation.
     *
     * @return array<string, mixed>
     */
    public function syncClaim(Claim $claim, ?User $actor = null): array
    {
        if (! $this->isConfigured()) {
            return $this->simulateClaim($claim);
        }

        $response = Http::withHeaders($this->headers())
            ->connectTimeout(3)
            ->timeout(15)
            ->post(rtrim((string) config('services.bpjs.eclaim_url'), '/').'/eclaim', $this->payload($claim));

        $result = $response->json();
        $code = (string) (data_get($result, 'metaData.code') ?? data_get($result, 'code') ?? $response->status());

        if (! is_array($result) || ! in_array($code, ['200', '201'], true)) {
            throw new RuntimeException(
                'Pengiriman E-Claim ditolak: '.data_get($result, 'metaData.message', "HTTP {$response->status()}"),
            );
        }

        $tariff = data_get($result, 'response.tarif_inacbg', data_get($result, 'response.tarif', $claim->tarif_ina_cbgs));
        $grouperCode = data_get($result, 'response.cbg_code', $claim->grouper_code);
        $previousStatus = $claim->status;

        $claim->update([
            'status' => ClaimStatus::Diajukan,
            'is_synced_eclaim' => true,
            'synced_at' => now(),
            'biaya_disetujui' => $tariff,
            'tarif_ina_cbgs' => $tariff,
            'grouper_code' => $grouperCode,
            'grouper_description' => data_get($result, 'response.cbg_description', $claim->grouper_description),
            'eclaim_response' => json_encode($result),
        ]);

        event(new ClaimStatusChanged($claim, $previousStatus, $actor));

        return [
            'status' => 'success',
            'code' => $code,
            'message' => "Klaim {$claim->nomor_klaim} berhasil dikirim ke E-Claim BPJS.",
            'cbg_code' => $grouperCode,
            'tarif' => $tariff,
            'simulated' => false,
            'payload' => $result,
        ];
    }

    /**
     * Create a pending batch record that can be dispatched to the queue.
     *
     * @param  array<int, int>  $claimIds
     */
    public function createBatch(array $claimIds, ?int $userId = null): EclaimSync
    {
        $claims = $this->readyClaims($claimIds);

        return EclaimSync::create([
            'hospital_id' => $claims->first()?->hospital_id,
            'initiated_by' => $userId,
            'batch_code' => 'BATCH-'.strtoupper(Str::random(4)).'-'.now()->format('ymd-His'),
            'tipe_sync' => $this->isConfigured() ? 'batch_pengajuan' : 'simulasi_lokal',
            'status' => EclaimSyncStatus::Pending,
            'total_items' => $claims->count(),
            'success_count' => 0,
            'failed_count' => 0,
        ]);
    }

    /**
     * Process a queued batch and persist per-item results.
     *
     * @param  array<int, int>  $claimIds
     */
    public function processBatch(EclaimSync $sync, array $claimIds): EclaimSync
    {
        $sync->update([
            'status' => EclaimSyncStatus::Processing,
            'started_at' => now(),
        ]);

        $success = 0;
        $failed = 0;
        $actor = $sync->initiator;

        foreach ($this->readyClaims($claimIds) as $claim) {
            try {
                $result = $this->syncClaim($claim, $actor);

                EclaimSyncItem::create([
                    'sync_id' => $sync->id,
                    'claim_id' => $claim->id,
                    'status' => EclaimItemStatus::Success,
                    'eclaim_response_code' => (string) ($result['code'] ?? ''),
                    'eclaim_response_message' => (string) $result['message'],
                    'request_payload' => $this->payload($claim),
                    'response_payload' => $result,
                ]);

                $success++;
            } catch (\Throwable $exception) {
                EclaimSyncItem::create([
                    'sync_id' => $sync->id,
                    'claim_id' => $claim->id,
                    'status' => EclaimItemStatus::Failed,
                    'eclaim_response_code' => '500',
                    'eclaim_response_message' => $exception->getMessage(),
                ]);

                $failed++;
            }
        }

        $sync->update([
            'status' => $failed > 0 ? EclaimSyncStatus::Failed : EclaimSyncStatus::Completed,
            'success_count' => $success,
            'failed_count' => $failed,
            'completed_at' => now(),
        ]);

        return $sync->load('items');
    }

    /**
     * Synchronous convenience wrapper retained for local tooling and tests.
     *
     * @param  array<int, int>  $claimIds
     */
    public function syncBatch(array $claimIds, ?int $userId = null): EclaimSync
    {
        $sync = $this->createBatch($claimIds, $userId);

        return $this->processBatch($sync, $claimIds);
    }

    /**
     * @param  array<int, int>  $claimIds
     * @return Collection<int, Claim>
     */
    private function readyClaims(array $claimIds): Collection
    {
        return Claim::with('patient')
            ->whereIn('id', $claimIds)
            ->readyToSubmit()
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Claim $claim): array
    {
        return [
            'nomor_sep' => $claim->no_sep,
            'nomor_klaim' => $claim->nomor_klaim,
            'nama_pasien' => $claim->patient?->nama_lengkap,
            'jenis_pelayanan' => $claim->jenis_pelayanan?->value,
            'tanggal_masuk' => $claim->tanggal_masuk?->format('Y-m-d'),
            'tanggal_pulang' => $claim->tanggal_pulang?->format('Y-m-d'),
            'diagnosa_primer' => $claim->diagnosa_primer,
            'prosedur_utama' => $claim->prosedur_utama,
            'estimasi_biaya' => (float) $claim->estimasi_biaya,
            'kode_grouper' => $claim->grouper_code,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $timestamp = (string) time();
        $consId = (string) config('services.bpjs.cons_id');
        $secretKey = (string) config('services.bpjs.secret_key');

        return [
            'X-cons-id' => $consId,
            'X-timestamp' => $timestamp,
            'X-signature' => base64_encode(hash_hmac('sha256', $consId.'&'.$timestamp, $secretKey, true)),
            'user_key' => (string) config('services.bpjs.user_key'),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateClaim(Claim $claim): array
    {
        $response = [
            'status' => 'SIMULATED_LOCAL',
            'message' => 'Klaim ini hanya disimulasikan lokal dan belum dikirim ke BPJS.',
            'nomor_sep' => $claim->no_sep,
            'timestamp' => now()->toIso8601String(),
        ];

        $claim->update([
            'eclaim_response' => json_encode($response),
        ]);

        return [
            'status' => 'simulated',
            'code' => 'SIM',
            'message' => "Klaim {$claim->nomor_klaim} Disimulasikan lokal (belum dikirim ke BPJS).",
            'simulated' => true,
            'payload' => $response,
        ];
    }
}
