<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'no_rm',
        'nama_lengkap',
        'jenis_kelamin',
        'tanggal_lahir',
        'usia',
        'no_ktp',
        'no_kis',
        'alamat',
        'telepon',
        'segmen_peserta',
        'kelas_hak',
        'faskes_tingkat_pertama',
        'is_active_bpjs',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'usia' => 'integer',
        'is_active_bpjs' => 'boolean',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }
}
