<?php

namespace App\Enums;

enum TipeDokumen: string
{
    case Sep = 'sep';
    case Rujukan = 'rujukan';
    case ResumeMedis = 'resume_medis';
    case RincianBiaya = 'rincian_biaya';
    case Penunjang = 'penunjang';

    public function label(): string
    {
        return match ($this) {
            self::Sep => 'Surat Eligibilitas Peserta',
            self::Rujukan => 'Surat Rujukan FKTP',
            self::ResumeMedis => 'Resume Medis Pasien Pulang',
            self::RincianBiaya => 'Rincian Biaya Rumah Sakit',
            self::Penunjang => 'Hasil Pemeriksaan Penunjang',
        };
    }
}
