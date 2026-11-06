<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ShiftStatus: string implements HasLabel, HasColor, HasIcon
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => 'En Operación (Abierto)',
            self::CLOSED => 'Cerrado y Conciliado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'info',
            self::CLOSED => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OPEN => 'heroicon-m-play-circle',
            self::CLOSED => 'heroicon-m-lock-closed',
        };
    }
}

