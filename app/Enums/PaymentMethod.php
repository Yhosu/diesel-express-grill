<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel, HasColor, HasIcon
{
    case EFECTIVO = 'Efectivo';
    case QR = 'QR';
    case TRANSFERENCIA = 'Transferencia';
    case OTRO = 'Otro';

    public function getLabel(): ?string
    {
        return $this->value;
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EFECTIVO => 'success',
            self::QR => 'info',
            self::TRANSFERENCIA => 'primary',
            self::OTRO => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EFECTIVO => 'heroicon-m-banknotes',
            self::QR => 'heroicon-m-qr-code',
            self::TRANSFERENCIA => 'heroicon-m-building-library',
            self::OTRO => 'heroicon-m-credit-card',
        };
    }

    public static function posOptions(): array
    {
        return [
            self::EFECTIVO->value => 'Efectivo (Bs.)',
            self::QR->value => 'Pago QR',
        ];
    }
}

