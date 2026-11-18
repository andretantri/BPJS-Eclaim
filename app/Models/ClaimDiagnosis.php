<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimDiagnosis extends Model
{
    protected $fillable = [
        'claim_id',
        'icd10_code_id',
        'kode_icd',
        'deskripsi',
        'tipe',
        'is_valid',
        'catatan_validasi',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function icd10Code(): BelongsTo
    {
        return $this->belongsTo(Icd10Code::class);
    }
}
