<?php

namespace App\Enums;

enum ClaimStatus: string
{
    case Baru = 'baru';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Bermasalah = 'bermasalah';
    case SiapDiajukan = 'siap_diajukan';
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::Bermasalah => 'Bermasalah',
            self::SiapDiajukan => 'Siap Diajukan',
            self::Diajukan => 'Diajukan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
