<?php

namespace Tests\Feature;

use App\Enums\ClaimStatus;
use App\Enums\NotificationPrioritas;
use App\Enums\StatusVerifikasi;
use App\Events\ClaimStatusChanged;
use App\Listeners\NotifyUsersOnClaimStatusChanged;
use App\Models\Claim;
use App\Models\User;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClaimGuardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClaimGuardSeeder::class);
        $this->user = User::where('email', 'nadia@cakramedika.com')->firstOrFail();
        $this->verifier = User::where('email', 'bima@cakramedika.com')->firstOrFail();
    }

    public function test_user_can_view_login_page_with_demo_accounts(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_authenticate_and_access_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('metrics')
            ->has('revenueData')
            ->has('priorityQueue')
            ->has('icd10Codes')
            ->has('icd9cmCodes')
        );
    }

    public function test_user_can_access_claims_index_and_filter(): void
    {
        $response = $this->actingAs($this->user)->get('/klaim?status=bermasalah');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Claims/Index')
            ->has('claims.data')
            ->has('selectedClaim')
            ->has('selectedClaim.diagnoses')
            ->has('selectedClaim.procedures')
            ->has('icd10Codes')
            ->has('icd9cmCodes')
        );
    }

    public function test_user_can_navigate_6_step_verification_workflow(): void
    {
        $blockedClaim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();

        $this->actingAs($this->user)
            ->get("/klaim/{$blockedClaim->id}/verifikasi/icd-10")
            ->assertRedirect("/klaim/{$blockedClaim->id}/verifikasi/pra-klaim");

        $claim = Claim::where('nomor_klaim', 'KLM-2026-0207')->firstOrFail();

        $steps = ['v-claim', 'pra-klaim', 'icd-10', 'icd-9', 'risiko-klaim', 'tinjauan-akhir'];
        foreach ($steps as $step) {
            $response = $this->actingAs($this->user)->get("/klaim/{$claim->id}/verifikasi/{$step}");
            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page
                ->component('Verification/Show')
                ->where('currentStep', $step)
                ->has('unlockedSteps')
                ->has('diagnoses')
                ->has('procedures')
            );
        }
    }

    public function test_uploading_signed_resume_resolves_blocker_and_marks_ready(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->first();
        $this->assertSame(ClaimStatus::Bermasalah, $claim->status);

        Storage::fake('local');
        $response = $this->actingAs($this->user)
            ->post("/klaim/{$claim->id}/verifikasi/upload-resume", $this->resumeUploadPayload());
        $response->assertSessionHas('success');

        $claim->refresh();
        $this->assertSame(ClaimStatus::SiapDiajukan, $claim->status);
        $this->assertTrue($claim->doc_resume);
        $this->assertEquals(0, $claim->jumlah_temuan_risiko);
    }

    public function test_uploading_resume_does_not_bypass_incomplete_verification_stages(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();
        $claim->verifications()->where('tahap', 'icd10')->update([
            'status' => StatusVerifikasi::Belum->value,
            'detail_hasil' => null,
        ]);

        Storage::fake('local');
        $response = $this->actingAs($this->user)
            ->post("/klaim/{$claim->id}/verifikasi/upload-resume", $this->resumeUploadPayload());

        $response->assertSessionHas('success');
        $this->assertStringContainsString(
            'masih menunggu tahap verifikasi lainnya',
            (string) $response->getSession()->get('success'),
        );

        $claim->refresh();
        $this->assertSame(ClaimStatus::Bermasalah, $claim->status);
        $this->assertTrue($claim->doc_resume);
        $this->assertEquals(0, $claim->jumlah_temuan_risiko);
    }

    public function test_user_can_access_eclaim_portal_and_trigger_batch_sync(): void
    {
        $response = $this->actingAs($this->user)->get('/eclaim');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Eclaim/Index')
            ->has('readyClaims')
            ->has('syncHistory')
        );

        $problemClaim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();
        Storage::fake('local');
        $this->actingAs($this->user)
            ->post("/klaim/{$problemClaim->id}/verifikasi/upload-resume", $this->resumeUploadPayload())
            ->assertSessionHas('success');

        $syncResponse = $this->actingAs($this->verifier)->post('/eclaim/sync-batch');
        $syncResponse->assertSessionHas('info');
        $this->assertStringContainsString('belum dikirim ke BPJS', (string) $syncResponse->getSession()->get('info'));
    }

    public function test_user_can_access_pelaporan_page(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('summary')
            ->has('topDiagnoses')
            ->has('monthlyTrend')
        );
    }

    public function test_update_status_dispatches_event_with_previous_status_and_actor(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();

        Event::fake([ClaimStatusChanged::class]);

        $this->actingAs($this->user)
            ->patch("/klaim/{$claim->id}/status", ['status' => ClaimStatus::SiapDiajukan->value])
            ->assertRedirect();

        Event::assertDispatched(fn (ClaimStatusChanged $event): bool => $event->claim->id === $claim->id
            && $event->previousStatus === ClaimStatus::Bermasalah
            && $event->claim->status === ClaimStatus::SiapDiajukan
            && $event->actor?->id === $this->user->id);
    }

    public function test_upload_resume_dispatches_event_with_previous_status_and_actor(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();

        Storage::fake('local');
        Event::fake([ClaimStatusChanged::class]);

        $this->actingAs($this->user)
            ->post("/klaim/{$claim->id}/verifikasi/upload-resume", $this->resumeUploadPayload())
            ->assertSessionHas('success');

        Event::assertDispatched(fn (ClaimStatusChanged $event): bool => $event->claim->id === $claim->id
            && $event->previousStatus === ClaimStatus::Bermasalah
            && $event->claim->status === ClaimStatus::SiapDiajukan
            && $event->actor?->id === $this->user->id);
    }

    public function test_return_for_revision_dispatches_event_with_previous_status_and_actor(): void
    {
        $claim = Claim::where('status', ClaimStatus::MenungguVerifikasi->value)->firstOrFail();

        Event::fake([ClaimStatusChanged::class]);

        $this->actingAs($this->verifier)
            ->post("/klaim/{$claim->id}/verifikasi/return-revision", ['catatan' => 'Mohon lengkapi tanda tangan DPJP.'])
            ->assertSessionHas('info');

        Event::assertDispatched(fn (ClaimStatusChanged $event): bool => $event->claim->id === $claim->id
            && $event->previousStatus === ClaimStatus::MenungguVerifikasi
            && $event->claim->status === ClaimStatus::Bermasalah
            && $event->actor?->id === $this->verifier->id);
    }

    public function test_mark_ready_dispatches_event_with_previous_status_and_actor(): void
    {
        $claim = Claim::where('status', ClaimStatus::MenungguVerifikasi->value)->firstOrFail();

        Event::fake([ClaimStatusChanged::class]);

        $this->actingAs($this->verifier)
            ->post("/klaim/{$claim->id}/verifikasi/mark-ready")
            ->assertSessionHas('success');

        Event::assertDispatched(fn (ClaimStatusChanged $event): bool => $event->claim->id === $claim->id
            && $event->previousStatus === ClaimStatus::MenungguVerifikasi
            && $event->claim->status === ClaimStatus::SiapDiajukan
            && $event->actor?->id === $this->verifier->id);
    }

    public function test_status_changed_listener_is_registered(): void
    {
        Event::fake([ClaimStatusChanged::class]);

        Event::assertListening(ClaimStatusChanged::class, NotifyUsersOnClaimStatusChanged::class);
    }

    public function test_status_changed_listener_notifies_team_without_notifying_actor(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();
        $claim->update(['status' => ClaimStatus::SiapDiajukan]);
        $admin = User::where('email', 'admin@cakramedika.com')->firstOrFail();

        (new NotifyUsersOnClaimStatusChanged)->handle(
            new ClaimStatusChanged($claim, ClaimStatus::Bermasalah, $this->user),
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->verifier->id,
            'claim_id' => $claim->id,
            'judul' => 'Status klaim berubah',
            'pesan' => "Klaim {$claim->nomor_klaim} berubah dari Bermasalah menjadi Siap Diajukan.",
            'prioritas' => NotificationPrioritas::Normal->value,
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'claim_id' => $claim->id,
            'judul' => 'Status klaim berubah',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'claim_id' => $claim->id,
            'judul' => 'Status klaim berubah',
        ]);
    }

    public function test_status_changed_listener_marks_problem_status_as_urgent(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();
        $claim->update(['status' => ClaimStatus::Bermasalah]);

        (new NotifyUsersOnClaimStatusChanged)->handle(
            new ClaimStatusChanged($claim, ClaimStatus::MenungguVerifikasi, $this->verifier),
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'claim_id' => $claim->id,
            'judul' => 'Status klaim berubah',
            'prioritas' => NotificationPrioritas::Urgent->value,
        ]);
    }

    public function test_verification_shortcut_redirects_to_patient_selection(): void
    {
        $this->actingAs($this->user)
            ->get('/v-claim')
            ->assertRedirect(route('verifikasi.select', ['step' => 'v-claim']));

        $this->actingAs($this->user)
            ->get(route('verifikasi.select', ['step' => 'v-claim']))
            ->assertInertia(fn ($page) => $page
                ->component('Verification/SelectPatient')
                ->where('selectedStep', 'v-claim')
                ->has('claims.data')
            );
    }

    public function test_uploading_resume_stores_a_private_document_and_allows_download(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();
        Storage::fake('local');

        $this->actingAs($this->user)
            ->post("/klaim/{$claim->id}/verifikasi/upload-resume", $this->resumeUploadPayload())
            ->assertSessionHas('success');

        $document = $claim->documents()->where('tipe_dokumen', 'resume_medis')->firstOrFail();
        $this->assertSame('resume-ttd.pdf', $document->nama_file);
        $this->assertNotNull($document->path_file);
        Storage::disk('local')->assertExists($document->path_file);

        $this->actingAs($this->user)
            ->get(route('verifikasi.document', ['claim' => $claim->id, 'document' => $document->id]))
            ->assertOk();
    }

    public function test_check_sep_persists_vclaim_verification_result(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();

        $this->actingAs($this->user)
            ->post("/klaim/{$claim->id}/verifikasi/check-sep", ['sep' => $claim->no_sep])
            ->assertSessionHas('info');

        $verification = $claim->verifications()->where('tahap', 'vclaim')->firstOrFail();
        $this->assertSame(StatusVerifikasi::Lolos, $verification->status);
        $this->assertTrue($verification->detail_hasil['simulated']);
    }

    public function test_mark_ready_is_blocked_when_a_document_is_still_incomplete(): void
    {
        $claim = Claim::where('status', ClaimStatus::MenungguVerifikasi->value)
            ->where('doc_toraks', false)
            ->firstOrFail();

        $this->actingAs($this->verifier)
            ->post("/klaim/{$claim->id}/verifikasi/mark-ready")
            ->assertSessionHas('error');

        $this->assertSame(ClaimStatus::MenungguVerifikasi, $claim->fresh()->status);
    }

    public function test_unknown_route_renders_custom_not_found_page(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan');
    }

    public function test_lazy_loading_is_blocked_outside_production(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $claim = Claim::query()->limit(2)->get()->firstOrFail();

        $this->assertTrue($claim->preventsLazyLoading);
        $this->expectException(LazyLoadingViolationException::class);

        $claim->documents;
    }

    /**
     * @return array<string, mixed>
     */
    private function resumeUploadPayload(): array
    {
        return [
            'tipe_dokumen' => 'resume_medis',
            'dokumen' => UploadedFile::fake()->create('resume-ttd.pdf', 128, 'application/pdf'),
            'catatan' => 'Tanda tangan DPJP telah diverifikasi.',
        ];
    }
}
