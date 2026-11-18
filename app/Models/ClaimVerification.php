<?php

namespace App\Models;

use App\Enums\StatusVerifikasi;
use App\Enums\TahapVerifikasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimVerification extends Model
{
    protected $fillable = [
        'claim_id',
        'tahap',
        'status',
        'verified_by',
        'detail_hasil',
        'catatan',
        'verified_at',
    ];

    protected $casts = [
        'tahap' => TahapVerifikasi::class,
        'status' => StatusVerifikasi::class,
        'detail_hasil' => 'array',
        'verified_at' => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
