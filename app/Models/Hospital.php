<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends Model
{
    protected $fillable = [
        'kode_rs',
        'nama_rs',
        'alamat',
        'kota',
        'provinsi',
        'telepon',
        'fax',
        'email',
        'kode_ppk_bpjs',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(ClaimPeriod::class);
    }

    public function eclaimSyncs(): HasMany
    {
        return $this->hasMany(EclaimSync::class);
    }
}
