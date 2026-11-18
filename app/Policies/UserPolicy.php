<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->viewAny($user) && $this->belongsToUserHospital($user, $target);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->view($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->view($user, $target) && $user->id !== $target->id;
    }

    public function impersonate(User $user, User $target): bool
    {
        return $this->view($user, $target)
            && $target->isActiveUser()
            && $user->id !== $target->id;
    }

    private function isAdmin(User $user): bool
    {
        $user->loadMissing('role');

        return $user->isAdmin();
    }

    private function belongsToUserHospital(User $user, User $target): bool
    {
        return $user->hospital_id !== null && $target->hospital_id === $user->hospital_id;
    }
}
