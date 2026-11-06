<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar Venta POS')
                ->icon('heroicon-o-shopping-cart')
                ->slideOver()
                ->modalHeading('Punto de Venta Móvil (POS) — Nuevo Registro')
                ->modalDescription('Complete el pedido seleccionando el producto, cantidad y método de pago.')
                ->modalWidth('4xl'),
        ];
    }
}
