<?php

namespace App\Models;

use App\Enums\StatusVerifikasi;
use App\Enums\TipeDokumen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimDocument extends Model
{
    protected $fillable = [
        'claim_id',
        'tipe_dokumen',
        'nama_file',
        'path_file',
        'ukuran_file',
        'mime_type',
        'status_verifikasi',
        'catatan_verifikasi',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'tipe_dokumen' => TipeDokumen::class,
        'status_verifikasi' => StatusVerifikasi::class,
        'ukuran_file' => 'integer',
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
