<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExpenseCategory: string implements HasLabel, HasColor
{
    case COMBUSTIBLE = 'Combustible';
    case PERMISOS = 'Permisos y licencias';
    case ALQUILER = 'Alquiler del espacio';
    case MANTENIMIENTO = 'Mantenimiento';
    case PERSONAL_FIJO = 'Personal fijo';
    case MARKETING = 'Marketing';
    case SERVICIOS = 'Servicios';
    case TRANSPORTE = 'Transporte';
    case LIMPIEZA = 'Limpieza adicional';
    case OTROS = 'Otros egresos';

    public function getLabel(): ?string
    {
        return $this->value;
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::COMBUSTIBLE, self::TRANSPORTE => 'warning',
            self::PERMISOS, self::ALQUILER => 'info',
            self::MANTENIMIENTO, self::LIMPIEZA => 'gray',
            self::PERSONAL_FIJO => 'primary',
            self::MARKETING => 'success',
            default => 'secondary',
        };
    }
}

