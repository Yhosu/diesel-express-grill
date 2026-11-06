<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\CostingService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        CostingService::recalculateProduct($this->record);
        $this->refreshFormData([
            'ingredients_cost',
            'packaging_cost',
            'consumables_cost',
            'production_var_cost',
            'direct_bom_cost',
            'master_unit_cost',
            'contribution_amount',
            'contribution_margin_pct',
        ]);
    }
}

