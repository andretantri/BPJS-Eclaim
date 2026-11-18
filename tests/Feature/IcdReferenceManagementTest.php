<?php

namespace Tests\Feature;

use App\Models\Icd10Code;
use App\Models\Icd9cmCode;
use App\Models\User;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IcdReferenceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClaimGuardSeeder::class);
        $this->admin = User::where('email', 'admin@cakramedika.com')->firstOrFail();
        $this->petugas = User::where('email', 'nadia@cakramedika.com')->firstOrFail();
    }

    public function test_admin_can_open_icd10_reference_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.icd.index', ['type' => 'icd10']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Icd/Index')
                ->where('type', 'icd10')
                ->where('label', 'ICD-10')
                ->has('codes.data'));
    }

    public function test_search_reports_existing_and_missing_icd10_code(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.icd.index', ['type' => 'icd10', 'q' => 'J18.9']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'J18.9')
                ->where('exactMatchExists', true));

        $this->actingAs($this->admin)
            ->get(route('admin.icd.index', ['type' => 'icd10', 'q' => 'I25.1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'I25.1')
                ->where('exactMatchExists', false));
    }

    public function test_admin_adds_missing_icd10_code(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.icd.store', ['type' => 'icd10']), [
                'type' => 'icd10',
                'kode' => 'i25.1',
                'deskripsi_id' => 'Penyakit aterosklerotik jantung',
                'deskripsi_en' => 'Atherosclerotic heart disease',
                'kategori' => 'Kardiovaskular',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.icd.index', ['type' => 'icd10']));

        $this->assertDatabaseHas('icd10_codes', [
            'kode' => 'I25.1',
            'deskripsi_id' => 'Penyakit aterosklerotik jantung',
            'is_active' => true,
        ]);
    }

    public function test_admin_adds_missing_icd9cm_code(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.icd.store', ['type' => 'icd9cm']), [
                'type' => 'icd9cm',
                'kode' => '88.56',
                'deskripsi_id' => 'Angiografi koroner',
                'kategori' => 'Diagnostik',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.icd.index', ['type' => 'icd9cm']));

        $this->assertDatabaseHas('icd9cm_codes', [
            'kode' => '88.56',
            'deskripsi_id' => 'Angiografi koroner',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_icd10_code_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.icd.store', ['type' => 'icd10']), [
                'type' => 'icd10',
                'kode' => 'J18.9',
                'deskripsi_id' => 'Pneumonia',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('kode');
    }

    public function test_admin_toggles_icd10_code_active_status(): void
    {
        $code = Icd10Code::where('kode', 'J18.9')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.icd.toggle', ['type' => 'icd10', 'icdCode' => $code->id]), [
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('icd10_codes', [
            'id' => $code->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('icd9cm_codes', [
            'kode' => '99.21',
            'is_active' => true,
        ]);
    }

    public function test_petugas_cannot_manage_icd_references(): void
    {
        $code = Icd10Code::where('kode', 'J18.9')->firstOrFail();

        $this->actingAs($this->petugas)
            ->get(route('admin.icd.index', ['type' => 'icd10']))
            ->assertForbidden();

        $this->actingAs($this->petugas)
            ->post(route('admin.icd.store', ['type' => 'icd10']), [
                'type' => 'icd10',
                'kode' => 'I25.1',
                'deskripsi_id' => 'Penyakit aterosklerotik jantung',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($this->petugas)
            ->patch(route('admin.icd.toggle', ['type' => 'icd10', 'icdCode' => $code->id]), [
                'is_active' => false,
            ])
            ->assertForbidden();
    }

    public function test_unknown_icd_reference_type_is_not_found(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.icd.index', ['type' => 'icd11']))
            ->assertNotFound();
    }

    public function test_icd9cm_reference_seeder_data_is_available(): void
    {
        $this->assertTrue(Icd9cmCode::where('kode', '99.21')->exists());
    }
}
