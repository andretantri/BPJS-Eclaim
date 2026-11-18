<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimProcedure extends Model
{
    protected $fillable = [
        'claim_id',
        'icd9cm_code_id',
        'kode_prosedur',
        'deskripsi',
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

    public function icd9cmCode(): BelongsTo
    {
        return $this->belongsTo(Icd9cmCode::class);
    }
}
