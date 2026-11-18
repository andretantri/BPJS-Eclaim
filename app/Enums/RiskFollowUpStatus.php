<?php

namespace App\Enums;

enum RiskFollowUpStatus: string
{
    case Baru = 'baru';
    case DalamProses = 'dalam_proses';
    case Terselesaikan = 'terselesaikan';
    case DiterimaRisiko = 'diterima_risiko';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::DalamProses => 'Dalam Proses',
            self::Terselesaikan => 'Terselesaikan',
            self::DiterimaRisiko => 'Diterima Risiko',
        };
    }
}
