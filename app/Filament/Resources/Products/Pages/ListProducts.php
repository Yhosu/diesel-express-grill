<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\CostingService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate_all')
                ->label('Recalcular Costos BOM')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function (): void {
                    CostingService::recalculateAllProducts();
                    Notification::make()
                        ->title('Costos Maestros recalculados desde las recetas BOM')
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->label('Nuevo Producto')
                ->slideOver()
                ->modalWidth('6xl')
                ->after(function (Product $record): void {
                    CostingService::recalculateProduct($record);
                }),
        ];
    }
}

