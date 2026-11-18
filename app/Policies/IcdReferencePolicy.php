<?php

namespace App\Policies;

use App\Models\User;

class IcdReferencePolicy
{
    public function viewAny(User $user): bool
    {
        $user->loadMissing('role');

        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }
}
