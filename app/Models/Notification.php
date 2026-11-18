<?php

namespace App\Models;

use App\Enums\NotificationPrioritas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'claim_id',
        'tipe',
        'judul',
        'pesan',
        'action_url',
        'prioritas',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'prioritas' => NotificationPrioritas::class,
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
