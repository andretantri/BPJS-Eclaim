<?php

namespace App\Enums;

enum TahapVerifikasi: string
{
    case Vclaim = 'vclaim';
    case PraKlaim = 'pra_klaim';
    case Icd10 = 'icd10';
    case Icd9cm = 'icd9cm';
    case Risiko = 'risiko';
    case TinjauanAkhir = 'tinjauan_akhir';

    public function label(): string
    {
        return match ($this) {
            self::Vclaim => 'BPJS V-Claim',
            self::PraKlaim => 'Verifikasi Pra-Klaim',
            self::Icd10 => 'Validasi ICD-10',
            self::Icd9cm => 'Validasi ICD-9-CM',
            self::Risiko => 'Deteksi Risiko Klaim',
            self::TinjauanAkhir => 'Tinjauan Akhir',
        };
    }

    public function step(): string
    {
        return match ($this) {
            self::Vclaim => 'v-claim',
            self::PraKlaim => 'pra-klaim',
            self::Icd10 => 'icd-10',
            self::Icd9cm => 'icd-9',
            self::Risiko => 'risiko-klaim',
            self::TinjauanAkhir => 'tinjauan-akhir',
        };
    }

    public static function fromStep(?string $step): ?self
    {
        foreach (self::cases() as $stage) {
            if ($stage->step() === $step) {
                return $stage;
            }
        }

        return null;
    }
}
