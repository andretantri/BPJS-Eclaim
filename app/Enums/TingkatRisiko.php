<?php

namespace App\Enums;

enum TingkatRisiko: string
{
    case Rendah = 'rendah';
    case Sedang = 'sedang';
    case Tinggi = 'tinggi';
    case Kritis = 'kritis';

    public function label(): string
    {
        return match ($this) {
            self::Rendah => 'Rendah',
            self::Sedang => 'Sedang',
            self::Tinggi => 'Tinggi',
            self::Kritis => 'Kritis',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function claimValues(): array
    {
        return [self::Rendah->value, self::Sedang->value, self::Tinggi->value];
    }
}
