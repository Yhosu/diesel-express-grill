<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ExceptionCode: string implements HasLabel, HasColor, HasIcon
{
    case W01 = 'W01';
    case W02 = 'W02';
    case W03 = 'W03';
    case C01 = 'C01';
    case S01 = 'S01';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::W01 => 'W01 - Deterioro (Merma)',
            self::W02 => 'W02 - Error de preparación (Merma)',
            self::W03 => 'W03 - Reposición a cliente (Merma)',
            self::C01 => 'C01 - Cortesía autorizada',
            self::S01 => 'S01 - Consumo del personal',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::W01, self::W02, self::W03 => 'danger',
            self::C01 => 'warning',
            self::S01 => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::W01, self::W02, self::W03 => 'heroicon-m-exclamation-triangle',
            self::C01 => 'heroicon-m-gift',
            self::S01 => 'heroicon-m-user-group',
        };
    }

    public function isWaste(): bool
    {
        return in_array($this, [self::W01, self::W02, self::W03], true);
    }

    public function isComplimentary(): bool
    {
        return $this === self::C01;
    }

    public function isStaff(): bool
    {
        return $this === self::S01;
    }
}

