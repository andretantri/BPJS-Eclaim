<?php

namespace App\Enums;

enum NotificationPrioritas: string
{
    case Rendah = 'rendah';
    case Normal = 'normal';
    case Tinggi = 'tinggi';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Rendah => 'Rendah',
            self::Normal => 'Normal',
            self::Tinggi => 'Tinggi',
            self::Urgent => 'Urgent',
        };
    }
}
