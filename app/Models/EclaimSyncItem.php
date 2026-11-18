<?php

namespace App\Models;

use App\Enums\EclaimItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EclaimSyncItem extends Model
{
    protected $fillable = [
        'sync_id',
        'claim_id',
        'status',
        'eclaim_response_code',
        'eclaim_response_message',
        'request_payload',
        'response_payload',
    ];

    protected $casts = [
        'status' => EclaimItemStatus::class,
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function sync(): BelongsTo
    {
        return $this->belongsTo(EclaimSync::class, 'sync_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
