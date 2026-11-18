<?php

namespace Tests\Feature;

use App\Enums\ClaimStatus;
use App\Enums\NotificationPrioritas;
use App\Models\Claim;
use App\Models\Hospital;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClaimGuardAuthorizationTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/klaim')->assertRedirect(route('login'));
    }

    public function test_user_without_claim_permission_cannot_view_claims(): void
    {
        $user = $this->makeUser([]);

        $this->actingAs($user)->get('/klaim')->assertForbidden();
    }

    public function test_inactive_user_cannot_access_authenticated_pages(): void
    {
        $user = $this->makeUser(['claims.view'], ['is_active' => false]);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_user_cannot_access_claim_from_another_hospital(): void
    {
        $foreignUser = $this->makeUser(['claims.view', 'claims.edit'], ['foreign' => true]);
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();

        $this->actingAs($foreignUser)
            ->get("/klaim/{$claim->id}/verifikasi/v-claim")
            ->assertNotFound();

        $this->actingAs($foreignUser)
            ->put("/klaim/{$claim->id}", $this->validClaimPayload(['status' => ClaimStatus::Baru->value]))
            ->assertNotFound();

        $this->actingAs($foreignUser)
            ->get('/klaim')
            ->assertInertia(fn ($page) => $page->where('claims.total', 0));
    }

    public function test_user_without_eclaim_sync_permission_cannot_sync_batch(): void
    {
        $this->actingAs($this->user)
            ->post('/eclaim/sync-batch')
            ->assertForbidden();
    }

    public function test_user_without_eclaim_sync_permission_cannot_sync_single_claim(): void
    {
        $claim = Claim::where('status', ClaimStatus::SiapDiajukan->value)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('eclaim.sync-single', $claim))
            ->assertForbidden();
    }

    public function test_store_claim_rejects_invalid_payload(): void
    {
        $payload = $this->validClaimPayload([
            'initial_status' => 'status_tidak_valid',
            'estimated_cost' => -1,
        ]);
        unset($payload['status'], $payload['sep_no']);

        $this->actingAs($this->user)
            ->post('/klaim', $payload)
            ->assertSessionHasErrors(['sep_no', 'initial_status', 'estimated_cost']);
    }

    public function test_store_claim_rejects_an_icd_code_outside_the_active_reference(): void
    {
        $payload = $this->validClaimPayload([
            'primary_diagnosis_code' => 'ZZ.99',
            'procedure_codes' => ['00.00'],
        ]);
        unset($payload['status']);

        $this->actingAs($this->user)
            ->post('/klaim', $payload)
            ->assertSessionHasErrors(['primary_diagnosis_code', 'procedure_codes.0']);

        $this->assertDatabaseMissing('claims', ['no_sep' => $payload['sep_no']]);
    }

    public function test_store_claim_creates_canonical_status(): void
    {
        $payload = $this->validClaimPayload(['initial_status' => ClaimStatus::Baru->value]);
        unset($payload['status']);

        $this->actingAs($this->user)
            ->post('/klaim', $payload)
            ->assertRedirect();

        $claim = Claim::where('no_sep', $payload['sep_no'])->firstOrFail();

        $this->assertDatabaseHas('claims', [
            'no_sep' => $payload['sep_no'],
            'status' => ClaimStatus::Baru->value,
            'hospital_id' => $this->user->hospital_id,
        ]);
        $this->assertDatabaseHas('claim_diagnoses', [
            'claim_id' => $claim->id,
            'kode_icd' => 'J18.9',
            'tipe' => 'primer',
        ]);
        $this->assertNull($claim->no_rujukan);
        $this->assertNull($claim->patient->no_kis);
        $this->assertSame('dr. Uji, Sp.PD', $claim->dpjp_nama);
        $this->assertFalse($claim->doc_sep);
        $this->assertFalse($claim->doc_rujukan);
        $this->assertFalse($claim->doc_resume);
        $this->assertFalse($claim->doc_billing);
        $this->assertFalse($claim->doc_toraks);
        $this->assertNull($claim->tarif_ina_cbgs);
        $this->assertNull($claim->tingkat_risiko);
        $this->assertSame(0, $claim->jumlah_temuan_risiko);
    }

    public function test_update_status_rejects_legacy_label(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();

        $this->actingAs($this->user)
            ->patch("/klaim/{$claim->id}/status", ['status' => 'Menunggu'])
            ->assertSessionHasErrors('status');
    }

    public function test_notifications_are_scoped_to_owner(): void
    {
        $mine = $this->makeNotification($this->user, 'Notifikasi saya');
        $other = $this->makeNotification($this->verifier, 'Notifikasi orang lain');
        $expectedUnread = Notification::where('user_id', $this->user->id)
            ->where('is_read', false)
            ->count();

        $this->actingAs($this->user)
            ->get('/notifikasi')
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->where('unreadCount', $expectedUnread)
                ->where('notifications.data', fn ($items): bool => $items->contains('id', $mine->id)
                    && $items->doesntContain('id', $other->id)));

        $this->actingAs($this->user)
            ->post(route('notifikasi.read', $other->id))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $other->id, 'is_read' => false]);
    }

    public function test_mark_all_read_only_updates_own_notifications(): void
    {
        $mine = $this->makeNotification($this->user, 'Notifikasi saya');
        $other = $this->makeNotification($this->verifier, 'Notifikasi orang lain');

        $this->actingAs($this->user)
            ->post(route('notifikasi.mark-read'))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', ['id' => $mine->id, 'is_read' => true]);
        $this->assertDatabaseHas('notifications', ['id' => $other->id, 'is_read' => false]);
    }

    public function test_notification_action_url_rejects_external_redirect(): void
    {
        $notification = $this->makeNotification($this->user, 'Notifikasi saya', 'https://evil.example/phishing');

        $this->actingAs($this->user)
            ->from('/notifikasi')
            ->post(route('notifikasi.read', $notification->id))
            ->assertRedirect('/notifikasi');

        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'is_read' => true]);
    }

    /**
     * @param  array<int, string>  $permissions
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(array $permissions, array $overrides = []): User
    {
        $hospital = ($overrides['foreign'] ?? false) === true
            ? Hospital::create([
                'kode_rs' => 'RS-LAIN-002',
                'nama_rs' => 'RS Seberang',
                'kota' => 'Bandung',
            ])
            : $this->user->hospital;

        $roleName = in_array('eclaim.sync', $permissions, true) ? 'verifikator_medis' : 'petugas_casemix';
        $role = Role::where('name', $roleName)->firstOrFail();
        $role->update(['permissions' => $permissions]);

        return User::factory()->create([
            'name' => 'Pengguna Uji',
            'hospital_id' => $hospital->id,
            'role_id' => $role->id,
            'is_active' => $overrides['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validClaimPayload(array $overrides = []): array
    {
        return array_merge([
            'patient_name' => 'Pasien Uji',
            'rm_no' => 'RM-'.Str::upper(Str::random(8)),
            'gender' => 'Perempuan',
            'age' => 34,
            'kis_no' => null,
            'sep_no' => 'SEP-'.Str::upper(Str::random(10)),
            'care_type' => 'Rawat inap · Kelas 2',
            'admission_date' => '2026-11-04',
            'discharge_date' => '2026-11-08',
            'dpjp' => 'dr. Uji, Sp.PD',
            'primary_diagnosis_code' => 'J18.9',
            'secondary_diagnosis_codes' => [],
            'procedure_codes' => [],
            'estimated_cost' => 1250000,
            'status' => ClaimStatus::Baru->value,
            'issue_note' => null,
        ], $overrides);
    }

    private function makeNotification(User $user, string $title, ?string $actionUrl = null): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'tipe' => 'info',
            'judul' => $title,
            'pesan' => 'Pesan uji untuk notifikasi.',
            'action_url' => $actionUrl,
            'prioritas' => NotificationPrioritas::Normal->value,
            'is_read' => false,
        ]);
    }
}
