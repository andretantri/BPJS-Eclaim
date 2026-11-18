<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\User;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClaimGuardSeeder::class);
        $this->user = User::where('email', 'nadia@cakramedika.com')->firstOrFail();
    }

    public function test_selection_lists_hospital_claims_with_chosen_verification_step(): void
    {
        $expectedTotal = Claim::forHospital($this->user->hospital_id)->count();
        $latestClaim = Claim::forHospital($this->user->hospital_id)->with('patient')->latest('id')->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('verifikasi.select', ['step' => 'icd-10']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Verification/SelectPatient')
                ->where('selectedStep', 'icd-10')
                ->where('selectedStepLabel', 'Validasi ICD-10')
                ->where('claims.total', $expectedTotal)
                ->where('claims.data.0.id', $latestClaim->id));
    }

    public function test_selection_filters_claims_by_patient_name(): void
    {
        $claim = Claim::forHospital($this->user->hospital_id)->with('patient')->firstOrFail();
        $patientName = $claim->patient->nama_lengkap;
        $expectedTotal = Claim::forHospital($this->user->hospital_id)
            ->whereHas('patient', fn ($query) => $query->where('nama_lengkap', $patientName))
            ->count();

        $this->actingAs($this->user)
            ->get(route('verifikasi.select', ['step' => 'v-claim', 'q' => $patientName]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', $patientName)
                ->where('claims.total', $expectedTotal)
                ->where('claims.data.0.id', $claim->id));
    }

    public function test_legacy_verification_navigation_redirects_to_patient_selection(): void
    {
        $this->actingAs($this->user)
            ->get(route('nav.icd10'))
            ->assertRedirect(route('verifikasi.select', ['step' => 'icd-10']));
    }
}
