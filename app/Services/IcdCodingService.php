<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\Icd10Code;
use App\Models\Icd9cmCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IcdCodingService
{
    /**
     * Replace the ICD codings of a claim and mirror canonical labels to the claim row.
     *
     * @param  array<int, string>  $secondaryCodes
     * @param  array<int, string>  $procedureCodes
     */
    public function sync(Claim $claim, string $primaryCode, array $secondaryCodes = [], array $procedureCodes = []): Claim
    {
        $primaryCode = strtoupper(trim($primaryCode));
        $secondaryCodes = $this->normalizeCodes($secondaryCodes, false);
        $procedureCodes = $this->normalizeCodes($procedureCodes, true);

        $primary = $this->findIcd10($primaryCode);
        $secondaries = $this->findIcd10ByCodes($secondaryCodes->all());
        $procedures = $this->findIcd9cmByCodes($procedureCodes->all());

        DB::transaction(function () use ($claim, $primaryCode, $primary, $secondaries, $procedures): void {
            $claim->diagnoses()->delete();
            $claim->procedures()->delete();

            $claim->diagnoses()->create([
                'icd10_code_id' => $primary?->id,
                'kode_icd' => $primaryCode,
                'deskripsi' => $primary?->deskripsi_id ?? 'Kode ICD-10 tidak ditemukan pada referensi aktif',
                'tipe' => 'primer',
                'is_valid' => $primary !== null,
                'catatan_validasi' => $primary === null ? 'Kode tidak ditemukan pada tabel referensi ICD-10 aktif.' : null,
            ]);

            foreach ($secondaries as $code => $icd) {
                $claim->diagnoses()->create([
                    'icd10_code_id' => $icd?->id,
                    'kode_icd' => $code,
                    'deskripsi' => $icd?->deskripsi_id ?? 'Kode ICD-10 tidak ditemukan pada referensi aktif',
                    'tipe' => 'sekunder',
                    'is_valid' => $icd !== null,
                    'catatan_validasi' => $icd === null ? 'Kode tidak ditemukan pada tabel referensi ICD-10 aktif.' : null,
                ]);
            }

            foreach ($procedures as $code => $icd) {
                $claim->procedures()->create([
                    'icd9cm_code_id' => $icd?->id,
                    'kode_prosedur' => $code,
                    'deskripsi' => $icd?->deskripsi_id ?? 'Kode ICD-9-CM tidak ditemukan pada referensi aktif',
                    'is_valid' => $icd !== null,
                    'catatan_validasi' => $icd === null ? 'Kode tidak ditemukan pada tabel referensi ICD-9-CM aktif.' : null,
                ]);
            }

            $claim->update([
                'diagnosa_primer' => $this->label($primaryCode, $primary?->deskripsi_id),
                'diagnosa_sekunder' => $this->joinLabels($secondaries->all()) ?: '-',
                'prosedur_utama' => $this->joinLabels($procedures->all()) ?: '-',
            ]);
        });

        return $claim->fresh();
    }

    /**
     * Backfill structured codings from the legacy free-text claim columns.
     */
    public function syncFromLegacy(Claim $claim): Claim
    {
        $primaryCode = $this->firstIcd10Code((string) $claim->diagnosa_primer) ?? '';
        $secondaryCodes = $this->extractIcd10Codes((string) $claim->diagnosa_sekunder)
            ->reject(fn (string $code): bool => $code === $primaryCode)
            ->values()
            ->all();
        $procedureCodes = $this->extractIcd9Codes((string) $claim->prosedur_utama)->all();

        if ($primaryCode === '' && $procedureCodes === []) {
            return $claim;
        }

        if ($primaryCode === '') {
            return $claim;
        }

        return $this->sync($claim, $primaryCode, $secondaryCodes, $procedureCodes);
    }

    /**
     * @return Collection<string, Icd10Code|null>
     */
    public function findIcd10ByCodes(array $codes): Collection
    {
        return $this->normalizeCodes($codes, false)
            ->mapWithKeys(fn (string $code): array => [$code => $this->findIcd10($code)]);
    }

    /**
     * @return Collection<string, Icd9cmCode|null>
     */
    public function findIcd9cmByCodes(array $codes): Collection
    {
        return $this->normalizeCodes($codes, true)
            ->mapWithKeys(fn (string $code): array => [$code => $this->findIcd9cm($code)]);
    }

    protected function findIcd10(string $code): ?Icd10Code
    {
        return Icd10Code::query()->where('kode', $code)->where('is_active', true)->first();
    }

    protected function findIcd9cm(string $code): ?Icd9cmCode
    {
        return Icd9cmCode::query()->where('kode', $code)->where('is_active', true)->first();
    }

    /**
     * @param  array<int, string>  $codes
     * @return Collection<int, string>
     */
    protected function normalizeCodes(array $codes, bool $uppercase = true): Collection
    {
        return collect($codes)
            ->map(fn (mixed $code): string => is_scalar($code) ? trim((string) $code) : '')
            ->filter()
            ->map(fn (string $code): string => $uppercase ? strtoupper($code) : $code)
            ->unique()
            ->values();
    }

    /**
     * @param  array<string, Icd10Code|Icd9cmCode|null>  $codes
     */
    protected function joinLabels(array $codes): string
    {
        return collect($codes)
            ->map(fn (Icd10Code|Icd9cmCode|null $icd, string $code): string => $this->label($code, $icd?->deskripsi_id))
            ->implode(', ');
    }

    protected function label(string $code, ?string $description): string
    {
        return filled($description) ? "{$code} {$description}" : $code;
    }

    protected function firstIcd10Code(string $text): ?string
    {
        return preg_match('/\b[A-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?\b/', strtoupper($text), $matches) === 1
            ? $matches[0]
            : null;
    }

    /**
     * @return Collection<int, string>
     */
    protected function extractIcd10Codes(string $text): Collection
    {
        preg_match_all('/\b[A-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?\b/', strtoupper($text), $matches);

        return collect($matches[0] ?? [])->unique()->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected function extractIcd9Codes(string $text): Collection
    {
        preg_match_all('/\b[0-9]{2}(?:\.[0-9]{1,2})?\b/', $text, $matches);

        return collect($matches[0] ?? [])->unique()->values();
    }
}
