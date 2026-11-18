<?php

namespace Tests\Feature;

use App\Enums\StatusVerifikasi;
use App\Models\Claim;
use App\Models\ClaimVerification;
use App\Models\User;
use App\Services\BpjsVclaimService;
use App\Services\IcdCodingService;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IcdVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClaimGuardSeeder::class);
        $this->user = User::where('email', 'nadia@cakramedika.com')->firstOrFail();
    }

    protected User $user;

    public function test_icd_search_endpoint_returns_active_reference_codes(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('api.icd10.search', ['q' => 'Pneumonia']))
            ->assertOk()
            ->assertJsonPath('data.0.kode', 'J18.9');

        $this->actingAs($this->user)
            ->getJson(route('api.icd9cm.search', ['q' => 'Foto toraks']))
            ->assertOk()
            ->assertJsonPath('data.0.kode', '87.44');
    }

    public function test_icd_search_requires_authentication(): void
    {
        $this->getJson(route('api.icd10.search'))
            ->assertUnauthorized();
    }

    public function test_validating_icd10_persists_the_result_on_the_claim(): void
    {
        $claim = $this->claim('KLM-2026-0207');

        $this->actingAs($this->user)
            ->post(route('verifikasi.validate-icd10', $claim))
            ->assertSessionHas('success');

        $verification = $this->verification($claim, 'icd10');
        $this->assertSame(StatusVerifikasi::Lolos, $verification->status);
        $this->assertSame('A09.9', $verification->detail_hasil['primer']);
    }

    public function test_validating_icd10_fails_when_the_primary_code_is_not_in_the_reference(): void
    {
        $claim = $this->claim('KLM-2026-0207');
        app(IcdCodingService::class)->sync($claim, 'ZZ.99', [], []);

        $this->actingAs($this->user)
            ->post(route('verifikasi.validate-icd10', $claim))
            ->assertSessionHas('error');

        $this->assertSame(StatusVerifikasi::Gagal, $this->verification($claim, 'icd10')->status);
    }

    public function test_validating_icd9cm_persists_the_result_on_the_claim(): void
    {
        $claim = $this->claim('KLM-2026-0207');

        $this->actingAs($this->user)
            ->post(route('verifikasi.validate-icd9', $claim))
            ->assertSessionHas('success');

        $verification = $this->verification($claim, 'icd9cm');
        $this->assertSame(StatusVerifikasi::Lolos, $verification->status);
        $this->assertContains('99.18', $verification->detail_hasil['kode']);
    }

    public function test_a_locked_stage_cannot_be_opened_or_validated(): void
    {
        $claim = $this->claim('KLM-2026-0184');
        ClaimVerification::where('claim_id', $claim->id)
            ->where('tahap', 'icd10')
            ->update(['status' => StatusVerifikasi::Belum->value, 'detail_hasil' => null]);

        $this->actingAs($this->user)
            ->get(route('verifikasi.show', ['claim' => $claim->id, 'step' => 'icd-10']))
            ->assertRedirect(route('verifikasi.show', ['claim' => $claim->id, 'step' => 'pra-klaim']));

        $this->actingAs($this->user)
            ->post(route('verifikasi.validate-icd10', $claim))
            ->assertSessionHas('error');

        $verification = $this->verification($claim, 'icd10');
        $this->assertSame(StatusVerifikasi::Belum, $verification->status);
        $this->assertNull($verification->detail_hasil);
    }

    public function test_risk_follow_up_updates_the_counter_and_risk_verification(): void
    {
        $claim = $this->claim('KLM-2026-0193');
        ClaimVerification::where('claim_id', $claim->id)
            ->where('tahap', 'pra_klaim')
            ->update(['status' => StatusVerifikasi::Lolos->value]);

        $this->actingAs($this->user)
            ->post(route('verifikasi.evaluate-risks', $claim))
            ->assertSessionHas('error');

        $this->assertSame(StatusVerifikasi::Gagal, $this->verification($claim, 'risiko')->status);

        $risk = $claim->risks()->firstOrFail();
        $this->actingAs($this->user)
            ->post(route('verifikasi.resolve-risk', ['claim' => $claim->id, 'risk' => $risk->id]), [
                'status' => 'diterima_risiko',
                'catatan' => 'Risiko diterima setelah klarifikasi DPJP.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, $claim->fresh()->jumlah_temuan_risiko);
        $this->assertSame('diterima_risiko', $risk->fresh()->status_tindak_lanjut->value);

        $this->actingAs($this->user)
            ->post(route('verifikasi.evaluate-risks', $claim))
            ->assertSessionHas('success');

        $this->assertSame(StatusVerifikasi::Lolos, $this->verification($claim, 'risiko')->status);
    }

    public function test_vclaim_simulator_does_not_invent_a_patient_for_an_unknown_sep(): void
    {
        $result = app(BpjsVclaimService::class)->searchSep('SEP-TIDAK-ADA');

        $this->assertSame('201', $result['metaData']['code']);
        $this->assertNull($result['response']);
        $this->assertTrue($result['simulated']);
        $this->assertStringNotContainsString('Dewi Larasati', json_encode($result, JSON_THROW_ON_ERROR));
    }

    private function claim(string $number): Claim
    {
        return Claim::where('nomor_klaim', $number)->firstOrFail();
    }

    private function verification(Claim $claim, string $stage): ClaimVerification
    {
        return $claim->verifications()->where('tahap', $stage)->firstOrFail();
    }
}
