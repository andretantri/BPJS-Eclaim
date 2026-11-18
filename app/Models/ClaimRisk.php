<?php

namespace App\Models;

use App\Enums\RiskFollowUpStatus;
use App\Enums\TingkatRisiko;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimRisk extends Model
{
    protected $fillable = [
        'claim_id',
        'kode_temuan',
        'judul_temuan',
        'deskripsi_temuan',
        'tingkat_risiko',
        'status_tindak_lanjut',
        'rekomendasi',
        'bukti_pendukung',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'tingkat_risiko' => TingkatRisiko::class,
        'status_tindak_lanjut' => RiskFollowUpStatus::class,
        'resolved_at' => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
