<?php

namespace App\Models;

use App\Enums\EclaimSyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EclaimSync extends Model
{
    protected $fillable = [
        'hospital_id',
        'initiated_by',
        'batch_code',
        'tipe_sync',
        'status',
        'total_items',
        'success_count',
        'failed_count',
        'error_log',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => EclaimSyncStatus::class,
        'error_log' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_items' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EclaimSyncItem::class, 'sync_id');
    }
}
