<?php

namespace App\Enums;

enum JenisPelayanan: string
{
    case RawatInap = 'rawat_inap';
    case RawatJalan = 'rawat_jalan';

    public function label(): string
    {
        return match ($this) {
            self::RawatInap => 'Rawat Inap',
            self::RawatJalan => 'Rawat Jalan',
        };
    }
}
