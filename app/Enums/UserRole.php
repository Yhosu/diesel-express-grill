<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel, HasColor, HasIcon
{
    case SUPER_ADMIN = 'super_admin';
    case SUB_ADMIN = 'sub_admin';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::SUB_ADMIN => 'Subadministrador',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SUPER_ADMIN => 'primary',
            self::SUB_ADMIN => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'heroicon-m-shield-check',
            self::SUB_ADMIN => 'heroicon-m-user-circle',
        };
    }

    public function canEditRecords(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    public function canDeleteRecords(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    public function canAccessAuditLogs(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    public function canManageUsers(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    public function canAccessReports(): bool
    {
        return true;
    }
}

