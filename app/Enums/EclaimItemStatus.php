<?php

namespace App\Enums;

enum EclaimItemStatus: string
{
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Berhasil',
            self::Failed => 'Gagal',
        };
    }
}
