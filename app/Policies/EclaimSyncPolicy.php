<?php

namespace App\Policies;

use App\Models\EclaimSync;
use App\Models\User;

class EclaimSyncPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('claims.view') || $user->hasPermission('eclaim.sync');
    }

    public function view(User $user, EclaimSync $sync): bool
    {
        return $this->viewAny($user)
            && $user->hospital_id !== null
            && $sync->hospital_id === $user->hospital_id;
    }

    public function sync(User $user): bool
    {
        return $user->hasPermission('eclaim.sync');
    }

    public function retry(User $user, EclaimSync $sync): bool
    {
        return $this->sync($user)
            && $user->hospital_id !== null
            && $sync->hospital_id === $user->hospital_id;
    }
}
