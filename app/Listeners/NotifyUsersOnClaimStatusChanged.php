<?php

namespace App\Listeners;

use App\Enums\ClaimStatus;
use App\Enums\NotificationPrioritas;
use App\Events\ClaimStatusChanged;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyUsersOnClaimStatusChanged implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ClaimStatusChanged $event): void
    {
        $previousLabel = $event->previousStatus?->label() ?? 'Baru';
        $currentLabel = $event->claim->status?->label() ?? 'Tidak diketahui';

        $recipients = User::where('hospital_id', $event->claim->hospital_id)
            ->where('is_active', true)
            ->when($event->actor, fn ($query, User $actor) => $query->where('id', '!=', $actor->id))
            ->with('role')
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('claims.verify'));

        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'claim_id' => $event->claim->id,
                'tipe' => 'info',
                'judul' => 'Status klaim berubah',
                'pesan' => "Klaim {$event->claim->nomor_klaim} berubah dari {$previousLabel} menjadi {$currentLabel}.",
                'action_url' => "/klaim/{$event->claim->id}/verifikasi/v-claim",
                'prioritas' => $event->claim->status === ClaimStatus::Bermasalah
                    ? NotificationPrioritas::Urgent
                    : NotificationPrioritas::Normal,
                'is_read' => false,
            ]);
        }
    }
}
