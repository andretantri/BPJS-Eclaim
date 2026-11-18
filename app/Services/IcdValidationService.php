<?php

namespace App\Services;

use App\Enums\RiskFollowUpStatus;
use App\Enums\StatusVerifikasi;
use App\Models\Claim;
use App\Models\Icd10Code;
use App\Models\Icd9cmCode;

class IcdValidationService
{
    /**
     * @return array<int, array{id: int, kode: string, deskripsi_id: string, kategori: string|null}>
     */
    public function searchIcd10(string $term): array
    {
        $term = trim($term);

        $query = Icd10Code::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('kode', 'like', "%{$term}%")
                        ->orWhere('deskripsi_id', 'like', "%{$term}%")
                        ->orWhere('deskripsi_en', 'like', "%{$term}%")
                        ->orWhere('kategori', 'like', "%{$term}%")
                        ->orWhere('sub_kategori', 'like', "%{$term}%");
                });
            });

        return $query->where('is_active', true)
            ->orderBy('kode')
            ->limit(20)
            ->get(['id', 'kode', 'deskripsi_id', 'kategori'])
            ->map(fn (Icd10Code $code): array => [
                'id' => $code->id,
                'kode' => $code->kode,
                'deskripsi_id' => $code->deskripsi_id,
                'kategori' => $code->kategori,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, kode: string, deskripsi_id: string, kategori: string|null}>
     */
    public function searchIcd9cm(string $term): array
    {
        $term = trim($term);

        $query = Icd9cmCode::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('kode', 'like', "%{$term}%")
                        ->orWhere('deskripsi_id', 'like', "%{$term}%")
                        ->orWhere('deskripsi_en', 'like', "%{$term}%")
                        ->orWhere('kategori', 'like', "%{$term}%");
                });
            });

        return $query->where('is_active', true)
            ->orderBy('kode')
            ->limit(20)
            ->get(['id', 'kode', 'deskripsi_id', 'kategori'])
            ->map(fn (Icd9cmCode $code): array => [
                'id' => $code->id,
                'kode' => $code->kode,
                'deskripsi_id' => $code->deskripsi_id,
                'kategori' => $code->kategori,
            ])
            ->all();
    }

    /**
     * @return array{status: StatusVerifikasi, catatan: string, detail: array<string, mixed>}
     */
    public function validateDiagnoses(Claim $claim): array
    {
        $claim->loadMissing('diagnoses.icd10Code');
        $diagnoses = $claim->diagnoses;
        $primers = $diagnoses->where('tipe', 'primer');
        $secondaries = $diagnoses->where('tipe', 'sekunder');
        $codes = $diagnoses->pluck('kode_icd')->all();

        $errors = [];

        if ($primers->count() !== 1) {
            $errors[] = 'Harus ada tepat satu diagnosis primer.';
        }

        if (count($codes) !== count(array_unique($codes))) {
            $errors[] = 'Kode diagnosis tidak boleh ganda.';
        }

        foreach ($diagnoses as $diagnosis) {
            if (! $diagnosis->is_valid || $diagnosis->icd10_code_id === null) {
                $errors[] = "Kode {$diagnosis->kode_icd} tidak cocok dengan referensi ICD-10 aktif.";
            }
        }

        return $this->result($errors, [
            'total' => $diagnoses->count(),
            'primer' => $primers->first()?->kode_icd,
            'sekunder' => $secondaries->pluck('kode_icd')->values()->all(),
        ]);
    }

    /**
     * @return array{status: StatusVerifikasi, catatan: string, detail: array<string, mixed>}
     */
    public function validateProcedures(Claim $claim): array
    {
        $claim->loadMissing('procedures.icd9cmCode');
        $procedures = $claim->procedures;
        $codes = $procedures->pluck('kode_prosedur')->all();

        $errors = [];

        if ($procedures->isEmpty()) {
            $errors[] = 'Minimal satu kode prosedur ICD-9-CM harus tercatat.';
        }

        if (count($codes) !== count(array_unique($codes))) {
            $errors[] = 'Kode prosedur tidak boleh ganda.';
        }

        foreach ($procedures as $procedure) {
            if (! $procedure->is_valid || $procedure->icd9cm_code_id === null) {
                $errors[] = "Kode {$procedure->kode_prosedur} tidak cocok dengan referensi ICD-9-CM aktif.";
            }
        }

        return $this->result($errors, [
            'total' => $procedures->count(),
            'kode' => $codes,
        ]);
    }

    /**
     * @return array{status: StatusVerifikasi, catatan: string, detail: array<string, mixed>}
     */
    public function evaluateRisks(Claim $claim): array
    {
        $claim->loadMissing('risks');
        $activeRisks = $claim->risks->filter(function ($risk): bool {
            $status = $risk->status_tindak_lanjut;

            return ! in_array(
                $status instanceof RiskFollowUpStatus ? $status->value : $status,
                [RiskFollowUpStatus::Terselesaikan->value, RiskFollowUpStatus::DiterimaRisiko->value],
                true,
            );
        });

        $errors = $activeRisks->isEmpty()
            ? []
            : [$activeRisks->count().' temuan risiko masih aktif dan perlu ditindaklanjuti.'];

        return $this->result($errors, [
            'aktif' => $activeRisks->count(),
            'kode_temuan' => $activeRisks->pluck('kode_temuan')->values()->all(),
        ]);
    }

    /**
     * @param  array<int, string>  $errors
     * @return array{status: StatusVerifikasi, catatan: string, detail: array<string, mixed>}
     */
    private function result(array $errors, array $detail): array
    {
        return [
            'status' => $errors === [] ? StatusVerifikasi::Lolos : StatusVerifikasi::Gagal,
            'catatan' => $errors === [] ? 'Validasi lolos sesuai referensi aktif.' : implode(' ', $errors),
            'detail' => $detail,
        ];
    }
}
