<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ReconciliationSemaphore: string implements HasLabel, HasColor, HasIcon
{
    case VERDE = 'VERDE';
    case AMARILLO = 'AMARILLO';
    case ROJO = 'ROJO';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::VERDE => 'VERDE (Conciliado)',
            self::AMARILLO => 'AMARILLO (Diferencia menor)',
            self::ROJO => 'ROJO (Revisar diferencia)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::VERDE => 'success',
            self::AMARILLO => 'warning',
            self::ROJO => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::VERDE => 'heroicon-m-check-circle',
            self::AMARILLO => 'heroicon-m-exclamation-circle',
            self::ROJO => 'heroicon-m-x-circle',
        };
    }

    public static function evaluate(float $difference): self
    {
        $abs = abs(round($difference, 2));
        if ($abs < 0.01) {
            return self::VERDE;
        }
        if ($abs <= 5.00) {
            return self::AMARILLO;
        }

        return self::ROJO;
    }
}

