<?php

namespace App\Enums;

enum UserRole: string
{
    case PetugasCasemix = 'petugas_casemix';
    case VerifikatorMedis = 'verifikator_medis';
    case AdminCasemix = 'admin_casemix';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::PetugasCasemix => 'Petugas Casemix',
            self::VerifikatorMedis => 'Verifikator Medis',
            self::AdminCasemix => 'Admin Casemix',
            self::Viewer => 'Viewer',
        };
    }
}
