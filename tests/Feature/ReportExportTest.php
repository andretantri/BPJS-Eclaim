<?php

namespace Tests\Feature;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\ClaimPeriod;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ClaimPeriod $activePeriod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClaimGuardSeeder::class);

        $this->user = User::where('email', 'nadia@cakramedika.com')->firstOrFail();
        $this->activePeriod = ClaimPeriod::where('hospital_id', $this->user->hospital_id)
            ->where('status', 'aktif')
            ->firstOrFail();
    }

    public function test_report_pages_require_authentication_and_view_permission(): void
    {
        $this->get(route('laporan.index'))->assertRedirect(route('login'));
        $this->get(route('laporan.export.excel'))->assertRedirect(route('login'));
        $this->get(route('laporan.export.pdf'))->assertRedirect(route('login'));

        $role = Role::where('name', 'petugas_casemix')->firstOrFail();
        $role->update(['permissions' => []]);
        $unauthorized = User::factory()->create([
            'hospital_id' => $this->user->hospital_id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($unauthorized)->get(route('laporan.index'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('laporan.export.excel'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('laporan.export.pdf'))->assertForbidden();
    }

    public function test_report_period_filter_is_scoped_to_current_hospital(): void
    {
        $foreignHospital = Hospital::create([
            'kode_rs' => 'RS-LAIN-002',
            'nama_rs' => 'RS Seberang',
            'kota' => 'Bandung',
        ]);
        $foreignPeriod = $this->makePeriod($foreignHospital->id, 'Desember 2026', 12, 2026, 'aktif');
        $this->makeClaim('KLM-LAIN-0001', $foreignHospital->id, $foreignPeriod->id, 99_999_999);

        $secondPeriod = $this->makePeriod($this->user->hospital_id, 'Oktober 2026', 10, 2026, 'ditutup');
        $this->makeClaim('KLM-OKT-0001', $this->user->hospital_id, $secondPeriod->id, 750_000);
        $activeCount = Claim::forHospital($this->user->hospital_id)
            ->where('period_id', $this->activePeriod->id)
            ->count();

        $this->actingAs($this->user)
            ->get(route('laporan.index', ['periode' => $foreignPeriod->id]))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->get(route('laporan.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Index')
                ->where('selectedPeriodId', $this->activePeriod->id)
                ->where('summary.totalKlaim', $activeCount)
                ->where('periods', fn ($periods): bool => collect($periods)->doesntContain('id', $foreignPeriod->id)));

        $this->actingAs($this->user)
            ->get(route('laporan.index', ['periode' => $secondPeriod->id]))
            ->assertInertia(fn ($page) => $page
                ->where('selectedPeriodId', $secondPeriod->id)
                ->where('summary.totalKlaim', 1)
                ->where('summary.totalBiaya', 750_000));

        $this->actingAs($this->user)
            ->get(route('laporan.index', ['periode' => 'all']))
            ->assertInertia(fn ($page) => $page
                ->where('selectedPeriodId', null)
                ->where('summary.totalKlaim', $activeCount + 1));
    }

    public function test_excel_export_returns_attachment_with_selected_period(): void
    {
        $secondPeriod = $this->makePeriod($this->user->hospital_id, 'Oktober 2026', 10, 2026, 'ditutup');
        $this->makeClaim('KLM-OKT-0002', $this->user->hospital_id, $secondPeriod->id, 1_250_000);

        $response = $this->actingAs($this->user)
            ->get(route('laporan.export.excel', ['periode' => $secondPeriod->id]));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('oktober-2026', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_export_returns_pdf_document(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('laporan.export.pdf', ['periode' => $this->activePeriod->id]));

        $response->assertOk();
        $this->assertStringContainsString('pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertTrue(str_starts_with((string) $response->getContent(), '%PDF'));
    }

    public function test_excel_export_rejects_foreign_period(): void
    {
        $foreignHospital = Hospital::create([
            'kode_rs' => 'RS-LAIN-003',
            'nama_rs' => 'RS Seberang Lain',
            'kota' => 'Bandung',
        ]);
        $foreignPeriod = $this->makePeriod($foreignHospital->id, 'Januari 2027', 1, 2027, 'aktif');

        $this->actingAs($this->user)
            ->get(route('laporan.export.excel', ['periode' => $foreignPeriod->id]))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->get(route('laporan.export.pdf', ['periode' => $foreignPeriod->id]))
            ->assertNotFound();
    }

    private function makePeriod(int $hospitalId, string $name, int $month, int $year, string $status): ClaimPeriod
    {
        return ClaimPeriod::create([
            'hospital_id' => $hospitalId,
            'nama_periode' => $name,
            'bulan' => $month,
            'tahun' => $year,
            'tanggal_mulai' => sprintf('%04d-%02d-01', $year, $month),
            'tanggal_selesai' => sprintf('%04d-%02d-28', $year, $month),
            'status' => $status,
        ]);
    }

    private function makeClaim(string $number, int $hospitalId, int $periodId, float $cost): Claim
    {
        $patient = Patient::create([
            'no_rm' => 'RM-'.Str::upper(Str::random(8)),
            'nama_lengkap' => 'Pasien Laporan',
            'jenis_kelamin' => 'Perempuan',
        ]);

        return Claim::create([
            'nomor_klaim' => $number,
            'patient_id' => $patient->id,
            'hospital_id' => $hospitalId,
            'period_id' => $periodId,
            'created_by' => $this->user->id,
            'no_sep' => 'SEP-'.$number,
            'tanggal_masuk' => '2026-11-10',
            'diagnosa_primer' => 'J18.9 Pneumonia',
            'estimasi_biaya' => $cost,
            'biaya_disetujui' => $cost,
            'status' => ClaimStatus::Disetujui->value,
        ]);
    }
}
