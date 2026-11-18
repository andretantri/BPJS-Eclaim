<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'hospital_id',
        'role_id',
        'nip',
        'jabatan',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'created_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function roleName(): ?UserRole
    {
        return UserRole::tryFrom($this->role?->name ?? '');
    }

    public function isActiveUser(): bool
    {
        return (bool) $this->is_active;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->isActiveUser() || $this->roleName() === null) {
            return false;
        }

        $permissions = $this->role->permissions ?? [];

        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }

        return $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->roleName() === UserRole::AdminCasemix;
    }

    public function isVerifier(): bool
    {
        return $this->roleName() === UserRole::VerifikatorMedis;
    }

    public function isPetugasCasemix(): bool
    {
        return $this->roleName() === UserRole::PetugasCasemix;
    }
}
