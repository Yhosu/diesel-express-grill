<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait InteractsWithUserRoles
{
    public static function currentAuthUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function canViewAny(): bool
    {
        return self::currentAuthUser() !== null;
    }

    public static function canView(Model $record): bool
    {
        return self::currentAuthUser() !== null;
    }

    public static function canCreate(): bool
    {
        return self::currentAuthUser() !== null;
    }

    public static function canEdit(Model $record): bool
    {
        return self::currentAuthUser()?->isSuperAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return self::currentAuthUser()?->isSuperAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return self::currentAuthUser()?->isSuperAdmin() ?? false;
    }
}

