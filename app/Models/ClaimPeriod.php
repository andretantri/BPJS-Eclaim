<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimPeriod extends Model
{
    protected $fillable = [
        'hospital_id',
        'nama_periode',
        'bulan',
        'tahun',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'total_nilai_klaim',
        'total_dibayar',
        'jumlah_klaim',
    ];

    protected $casts = [
        'bulan' => 'integer',
        'tahun' => 'integer',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'total_nilai_klaim' => 'decimal:2',
        'total_dibayar' => 'decimal:2',
        'jumlah_klaim' => 'integer',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'period_id');
    }
}
