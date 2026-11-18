<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Services\IcdCodingService;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IcdCodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClaimGuardSeeder::class);
    }

    public function test_seeded_claim_stores_icd_codings_with_reference_foreign_keys(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();

        $primary = $claim->diagnoses()->where('tipe', 'primer')->firstOrFail();
        $secondary = $claim->diagnoses()->where('tipe', 'sekunder')->firstOrFail();
        $procedure = $claim->procedures()->firstOrFail();

        $this->assertSame('J18.9', $primary->kode_icd);
        $this->assertNotNull($primary->icd10_code_id);
        $this->assertSame('Pneumonia, tidak spesifik', $primary->deskripsi);

        $this->assertSame('E11.9', $secondary->kode_icd);
        $this->assertNotNull($secondary->icd10_code_id);

        $this->assertSame('99.21', $procedure->kode_prosedur);
        $this->assertNotNull($procedure->icd9cm_code_id);

        $this->assertStringStartsWith('J18.9 ', $claim->diagnosa_primer);
    }

    public function test_sync_replaces_codings_and_mirrors_canonical_reference_labels(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();

        app(IcdCodingService::class)->sync($claim, 'K35.8', ['I10'], ['47.09']);
        $claim->refresh();

        $this->assertSame(
            ['K35.8', 'I10'],
            $claim->diagnoses()->orderBy('id')->pluck('kode_icd')->all(),
        );
        $this->assertSame(
            ['47.09'],
            $claim->procedures()->orderBy('id')->pluck('kode_prosedur')->all(),
        );
        $this->assertSame('K35.8 Apendisitis akut lainnya dan tidak spesifik', $claim->diagnosa_primer);
        $this->assertSame('I10 Hipertensi esensial (primer)', $claim->diagnosa_sekunder);
        $this->assertSame('47.09 Laparoskopi apendektomi / operasi usus buntu', $claim->prosedur_utama);
    }

    public function test_sync_flags_a_code_that_is_missing_from_the_active_reference(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();

        app(IcdCodingService::class)->sync($claim, 'ZZ.99', [], []);
        $claim->refresh();

        $primary = $claim->diagnoses()->where('tipe', 'primer')->firstOrFail();
        $this->assertNull($primary->icd10_code_id);
        $this->assertFalse($primary->is_valid);
        $this->assertStringContainsString('referensi ICD-10 aktif', (string) $primary->catatan_validasi);
    }

    public function test_sync_from_legacy_text_extracts_icd_codes_from_claim_columns(): void
    {
        $claim = Claim::where('nomor_klaim', 'KLM-2026-0184')->firstOrFail();
        $claim->update([
            'diagnosa_primer' => 'N20.0 Batu ginjal',
            'diagnosa_sekunder' => 'N13.2 Hidronefrosis',
            'prosedur_utama' => '59.8 (Ureteroskopi & DJ Stent)',
        ]);

        app(IcdCodingService::class)->syncFromLegacy($claim);
        $claim->refresh();

        $this->assertSame(
            ['N20.0', 'N13.2'],
            $claim->diagnoses()->orderBy('id')->pluck('kode_icd')->all(),
        );
        $this->assertSame('59.8', $claim->procedures()->firstOrFail()->kode_prosedur);
    }
}
