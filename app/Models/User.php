<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'is_active',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    public function isSuperAdmin(): bool
    {
        $role = $this->role instanceof UserRole
            ? $this->role
            : UserRole::tryFrom((string) $this->role);

        return $role === UserRole::SUPER_ADMIN;
    }

    public function isSubAdmin(): bool
    {
        $role = $this->role instanceof UserRole
            ? $this->role
            : UserRole::tryFrom((string) $this->role);

        return $role === UserRole::SUB_ADMIN;
    }

    public function canEditRecords(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canDeleteRecords(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canAccessAuditLogs(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canAccessReports(): bool
    {
        return $this->isSuperAdmin() || $this->isSubAdmin();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
