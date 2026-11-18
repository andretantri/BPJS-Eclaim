<?php

namespace Tests\Feature;

use App\Enums\ClaimStatus;
use App\Enums\EclaimItemStatus;
use App\Enums\EclaimSyncStatus;
use App\Jobs\SyncEclaimBatch;
use App\Models\Claim;
use App\Models\EclaimSync;
use App\Models\Hospital;
use App\Models\User;
use App\Services\BpjsEclaimService;
use App\Services\BpjsVclaimService;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BpjsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClaimGuardSeeder::class);
    }

    public function test_vclaim_uses_real_adapter_when_credentials_are_configured(): void
    {
        $this->configureBpjs();
        Http::fake([
            '*' => Http::response([
                'metaData' => ['code' => '200', 'message' => 'Sukses'],
                'response' => ['noSep' => 'SIM-SEP-261104-0184'],
            ], 200),
        ]);

        $result = app(BpjsVclaimService::class)->searchSep('SIM-SEP-261104-0184');

        $this->assertFalse($result['simulated']);
        $this->assertSame('200', data_get($result, 'metaData.code'));
        Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('X-cons-id', 'CONS-123')
            && str_contains($request->url(), 'SIM-SEP-261104-0184'));
    }

    public function test_vclaim_simulation_is_explicit_when_credentials_are_missing(): void
    {
        $this->clearBpjsConfig();
        Http::fake();

        $result = app(BpjsVclaimService::class)->searchSep('SIM-SEP-261104-0184');

        $this->assertTrue($result['simulated']);
        $this->assertSame('200', data_get($result, 'metaData.code'));
        $this->assertStringContainsString('Simulasi Lokal', (string) data_get($result, 'metaData.message'));
        Http::assertNothingSent();
    }

    public function test_vclaim_simulation_does_not_claim_bpjs_participant_status(): void
    {
        $this->clearBpjsConfig();
        Http::fake();

        $claim = Claim::where('no_sep', 'SIM-SEP-261104-0184')->firstOrFail();
        $service = app(BpjsVclaimService::class);

        $result = $service->searchSep($claim->no_sep);

        $this->assertTrue($result['simulated']);
        $this->assertSame('Belum diverifikasi', data_get($result, 'response.peserta.statusPeserta.keterangan'));
        $this->assertFalse(data_get($result, 'response.is_match'));

        $participant = $service->verifyParticipant((string) $claim->patient->no_kis);

        $this->assertTrue($participant['simulated']);
        $this->assertSame('tidak_diverifikasi', $participant['status']);
        $this->assertFalse($participant['peserta_aktif']);
        $this->assertFalse($participant['hak_layanan_sesuai']);
        Http::assertNothingSent();
    }

    public function test_eclaim_real_adapter_marks_claim_as_submitted(): void
    {
        $this->configureBpjs();
        Http::fake([
            '*' => Http::response([
                'metaData' => ['code' => '200', 'message' => 'Sukses'],
                'response' => [
                    'tarif_inacbg' => 7850000,
                    'cbg_code' => 'J-4-16-I',
                    'cbg_description' => 'INA-CBGs terverifikasi',
                ],
            ], 200),
        ]);

        $claim = Claim::where('status', ClaimStatus::SiapDiajukan->value)->firstOrFail();
        $claim->update(['is_synced_eclaim' => false]);

        $result = app(BpjsEclaimService::class)->syncClaim($claim);
        $claim->refresh();

        $this->assertFalse($result['simulated']);
        $this->assertSame('200', $result['code']);
        $this->assertSame(ClaimStatus::Diajukan, $claim->status);
        $this->assertTrue($claim->is_synced_eclaim);
        Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('X-cons-id', 'CONS-123')
            && str_contains($request->url(), 'eclaim'));
    }

    public function test_eclaim_without_grouper_response_does_not_invent_a_code(): void
    {
        $this->configureBpjs();
        Http::fake([
            '*' => Http::response([
                'metaData' => ['code' => '200', 'message' => 'Sukses'],
                'response' => ['tarif_inacbg' => 7850000],
            ], 200),
        ]);

        $claim = $this->readyClaim();
        $claim->update(['grouper_code' => null]);

        $result = app(BpjsEclaimService::class)->syncClaim($claim->refresh());

        $this->assertFalse($result['simulated']);
        $this->assertNull($claim->fresh()->grouper_code);
    }

    public function test_eclaim_simulation_does_not_claim_bpjs_success(): void
    {
        $this->clearBpjsConfig();
        Http::fake();

        $claim = Claim::where('status', ClaimStatus::SiapDiajukan->value)->firstOrFail();
        $claim->update(['is_synced_eclaim' => false]);

        $result = app(BpjsEclaimService::class)->syncClaim($claim);
        $claim->refresh();

        $this->assertTrue($result['simulated']);
        $this->assertSame('SIM', $result['code']);
        $this->assertStringContainsString('belum dikirim ke BPJS', (string) $result['message']);
        $this->assertSame(ClaimStatus::SiapDiajukan, $claim->status);
        $this->assertFalse($claim->is_synced_eclaim);
        $this->assertStringContainsString('SIMULATED_LOCAL', (string) $claim->eclaim_response);
        Http::assertNothingSent();
    }

    public function test_batch_sync_maps_failed_item_to_failed_status(): void
    {
        $this->configureBpjs();
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'metaData' => ['code' => '200', 'message' => 'Sukses'],
                    'response' => ['tarif_inacbg' => 7850000],
                ], 200)
                ->push([
                    'metaData' => ['code' => '500', 'message' => 'Ditolak BPJS'],
                ], 500),
        ]);

        $first = Claim::where('status', ClaimStatus::SiapDiajukan->value)->firstOrFail();
        $second = Claim::where('status', ClaimStatus::Bermasalah->value)->firstOrFail();
        $first->update(['is_synced_eclaim' => false]);
        $second->update([
            'status' => ClaimStatus::SiapDiajukan,
            'is_synced_eclaim' => false,
        ]);

        $sync = app(BpjsEclaimService::class)->syncBatch([$first->id, $second->id]);

        $this->assertSame(EclaimSyncStatus::Failed, $sync->status);
        $this->assertSame(1, $sync->success_count);
        $this->assertSame(1, $sync->failed_count);
        $this->assertSame(1, $sync->items()->where('status', EclaimItemStatus::Success->value)->count());
        $this->assertSame(1, $sync->items()->where('status', EclaimItemStatus::Failed->value)->count());
    }

    public function test_sync_batch_queues_job_and_keeps_batch_pending(): void
    {
        $this->clearBpjsConfig();
        $claim = $this->readyClaim();
        $verifier = User::where('email', 'bima@cakramedika.com')->firstOrFail();

        Queue::fake([SyncEclaimBatch::class]);

        $response = $this->actingAs($verifier)->post('/eclaim/sync-batch', ['claim_ids' => [$claim->id]]);

        $response->assertRedirect();
        $response->assertSessionHas('info');

        $sync = EclaimSync::latest('id')->firstOrFail();
        $this->assertSame(EclaimSyncStatus::Pending, $sync->status);
        $this->assertSame('simulasi_lokal', $sync->tipe_sync);
        $this->assertSame(1, $sync->total_items);
        $this->assertSame(0, $sync->items()->count());

        Queue::assertPushed(SyncEclaimBatch::class, fn (SyncEclaimBatch $job): bool => $job->sync->id === $sync->id
            && $job->claimIds === [$claim->id]);

        $this->assertFalse($claim->refresh()->is_synced_eclaim);
    }

    public function test_queued_batch_job_processes_pending_batch(): void
    {
        $this->clearBpjsConfig();
        Http::preventStrayRequests();
        $claim = $this->readyClaim();
        $verifier = User::where('email', 'bima@cakramedika.com')->firstOrFail();

        $sync = app(BpjsEclaimService::class)->createBatch([$claim->id], $verifier->id);
        $this->assertSame(EclaimSyncStatus::Pending, $sync->status);

        SyncEclaimBatch::dispatch($sync, [$claim->id]);

        $sync->refresh();
        $this->assertSame(EclaimSyncStatus::Completed, $sync->status);
        $this->assertSame(1, $sync->success_count);
        $this->assertSame(0, $sync->failed_count);
        $this->assertSame(1, $sync->items()->where('status', EclaimItemStatus::Success->value)->count());
        $claim->refresh();
        $this->assertStringContainsString('SIMULATED_LOCAL', (string) $claim->eclaim_response);
        $this->assertFalse($claim->is_synced_eclaim);
    }

    public function test_retry_queues_new_batch_for_failed_claims(): void
    {
        $this->configureBpjs();
        Http::preventStrayRequests();
        Http::fake([
            'eclaim.test/*' => Http::response([
                'metaData' => ['code' => '500', 'message' => 'Ditolak BPJS'],
            ], 500),
        ]);
        $claim = $this->readyClaim();
        $verifier = User::where('email', 'bima@cakramedika.com')->firstOrFail();

        $failedSync = app(BpjsEclaimService::class)->syncBatch([$claim->id], $verifier->id);
        $this->assertSame(EclaimSyncStatus::Failed, $failedSync->status);
        $this->assertSame(1, $failedSync->failed_count);

        Queue::fake([SyncEclaimBatch::class]);

        $response = $this->actingAs($verifier)->post(route('eclaim.retry', $failedSync));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $retrySync = EclaimSync::latest('id')->firstOrFail();
        $this->assertNotSame($failedSync->id, $retrySync->id);
        $this->assertSame(EclaimSyncStatus::Pending, $retrySync->status);
        $this->assertSame(1, $retrySync->total_items);
        $this->assertSame(EclaimSyncStatus::Failed, $failedSync->fresh()->status);

        Queue::assertPushed(SyncEclaimBatch::class, fn (SyncEclaimBatch $job): bool => $job->sync->id === $retrySync->id
            && $job->claimIds === [$claim->id]);
    }

    public function test_retry_forbids_user_without_eclaim_sync_permission(): void
    {
        $claim = $this->readyClaim();
        $sync = app(BpjsEclaimService::class)->createBatch([$claim->id]);
        $petugas = User::where('email', 'nadia@cakramedika.com')->firstOrFail();
        $syncCount = EclaimSync::count();

        Queue::fake([SyncEclaimBatch::class]);

        $this->actingAs($petugas)
            ->post(route('eclaim.retry', $sync))
            ->assertForbidden();

        $this->assertSame($syncCount, EclaimSync::count());
        Queue::assertNothingPushed();
    }

    public function test_retry_returns_404_for_another_hospitals_batch(): void
    {
        $claim = $this->readyClaim();
        $foreignHospital = Hospital::create([
            'kode_rs' => 'RS-LAIN-902',
            'nama_rs' => 'RS Uji Lain',
            'kota' => 'Bandung',
        ]);
        $foreignSync = EclaimSync::create([
            'hospital_id' => $foreignHospital->id,
            'batch_code' => 'BATCH-FOREIGN-01',
            'tipe_sync' => 'batch_pengajuan',
            'status' => EclaimSyncStatus::Failed,
            'total_items' => 1,
        ]);
        $verifier = User::where('email', 'bima@cakramedika.com')->firstOrFail();

        Queue::fake([SyncEclaimBatch::class]);

        $this->actingAs($verifier)
            ->post(route('eclaim.retry', $foreignSync))
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_retry_without_failed_items_returns_warning_and_dispatches_nothing(): void
    {
        $claim = $this->readyClaim();
        $sync = app(BpjsEclaimService::class)->createBatch([$claim->id]);
        $verifier = User::where('email', 'bima@cakramedika.com')->firstOrFail();
        $syncCount = EclaimSync::count();

        Queue::fake([SyncEclaimBatch::class]);

        $response = $this->actingAs($verifier)->post(route('eclaim.retry', $sync));

        $response->assertSessionHas('warning');
        $this->assertSame($syncCount, EclaimSync::count());
        Queue::assertNothingPushed();
    }

    public function test_batch_job_timeout_and_unique_lock_stay_within_queue_retry_window(): void
    {
        $job = new SyncEclaimBatch(new EclaimSync, []);
        $redisRetryAfter = (int) config('queue.connections.redis.retry_after');
        $databaseRetryAfter = (int) config('queue.connections.database.retry_after');

        $this->assertGreaterThan($job->timeout, $job->uniqueFor);
        $this->assertLessThan($redisRetryAfter, $job->uniqueFor);
        $this->assertLessThan($databaseRetryAfter, $job->uniqueFor);
    }

    private function readyClaim(): Claim
    {
        $claim = Claim::where('status', ClaimStatus::SiapDiajukan->value)
            ->where('is_synced_eclaim', true)
            ->firstOrFail();
        $claim->update(['is_synced_eclaim' => false]);

        return $claim->refresh();
    }

    private function configureBpjs(): void
    {
        config()->set('services.bpjs.vclaim_url', 'https://vclaim.test');
        config()->set('services.bpjs.eclaim_url', 'https://eclaim.test');
        config()->set('services.bpjs.cons_id', 'CONS-123');
        config()->set('services.bpjs.secret_key', 'SECRET-123');
        config()->set('services.bpjs.user_key', 'USER-123');
    }

    private function clearBpjsConfig(): void
    {
        config()->set('services.bpjs.vclaim_url', null);
        config()->set('services.bpjs.eclaim_url', null);
        config()->set('services.bpjs.cons_id', null);
        config()->set('services.bpjs.secret_key', null);
        config()->set('services.bpjs.user_key', null);
    }
}
