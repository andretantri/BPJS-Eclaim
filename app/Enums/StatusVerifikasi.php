<?php

namespace App\Enums;

enum StatusVerifikasi: string
{
    case Belum = 'belum';
    case Lolos = 'lolos';
    case Gagal = 'gagal';
    case Lengkap = 'lengkap';
    case TidakLengkap = 'tidak_lengkap';
    case PerluRevisi = 'perlu_revisi';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum',
            self::Lolos => 'Lolos',
            self::Gagal => 'Gagal',
            self::Lengkap => 'Lengkap',
            self::TidakLengkap => 'Tidak Lengkap',
            self::PerluRevisi => 'Perlu Revisi',
        };
    }
}
