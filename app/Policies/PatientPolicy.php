<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('claims.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->viewAny($user) && $this->isVisibleToHospital($user, $patient);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('claims.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->hasPermission('claims.edit') && $this->isVisibleToHospital($user, $patient);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->hasPermission('claims.edit') && $this->isVisibleToHospital($user, $patient);
    }

    private function isVisibleToHospital(User $user, Patient $patient): bool
    {
        if ($user->hospital_id === null) {
            return false;
        }

        return $patient->claims()
            ->where('hospital_id', $user->hospital_id)
            ->exists();
    }
}
