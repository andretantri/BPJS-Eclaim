<?php

namespace App\Policies;

use App\Models\Claim;
use App\Models\User;

class ClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('claims.view');
    }

    public function view(User $user, Claim $claim): bool
    {
        return $user->hasPermission('claims.view') && $this->belongsToUserHospital($user, $claim);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('claims.create');
    }

    public function update(User $user, Claim $claim): bool
    {
        return $user->hasPermission('claims.edit') && $this->belongsToUserHospital($user, $claim);
    }

    public function delete(User $user, Claim $claim): bool
    {
        return $user->hasPermission('claims.edit') && $this->belongsToUserHospital($user, $claim);
    }

    public function verify(User $user, Claim $claim): bool
    {
        return $user->hasPermission('claims.verify') && $this->belongsToUserHospital($user, $claim);
    }

    public function approve(User $user, Claim $claim): bool
    {
        return $user->hasPermission('claims.approve') && $this->belongsToUserHospital($user, $claim);
    }

    public function syncEclaim(User $user, Claim $claim): bool
    {
        return $user->hasPermission('eclaim.sync') && $this->belongsToUserHospital($user, $claim);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('claims.view');
    }

    private function belongsToUserHospital(User $user, Claim $claim): bool
    {
        return $user->hospital_id !== null && $claim->hospital_id === $user->hospital_id;
    }
}
