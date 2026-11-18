<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActiveUser();
    }

    public function view(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    public function markAsRead(User $user, Notification $notification): bool
    {
        return $this->view($user, $notification);
    }

    public function markAllRead(User $user): bool
    {
        return $user->isActiveUser();
    }
}
