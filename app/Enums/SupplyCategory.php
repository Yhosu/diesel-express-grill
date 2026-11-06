<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SupplyCategory: string implements HasLabel, HasColor, HasIcon
{
    case INGREDIENTE = 'Ingrediente';
    case BEBIDA = 'Bebida';
    case EMPAQUE = 'Empaque';
    case CONSUMIBLE = 'Consumible';
    case PRODUCCION = 'Producción';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::INGREDIENTE => 'Ingrediente',
            self::BEBIDA => 'Bebida',
            self::EMPAQUE => 'Empaque (Packaging)',
            self::CONSUMIBLE => 'Consumible',
            self::PRODUCCION => 'Producción Variable',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::INGREDIENTE => 'success',
            self::BEBIDA => 'info',
            self::EMPAQUE => 'warning',
            self::CONSUMIBLE => 'gray',
            self::PRODUCCION => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::INGREDIENTE => 'heroicon-m-cube',
            self::BEBIDA => 'heroicon-m-beaker',
            self::EMPAQUE => 'heroicon-m-archive-box',
            self::CONSUMIBLE => 'heroicon-m-sparkles',
            self::PRODUCCION => 'heroicon-m-fire',
        };
    }
}

