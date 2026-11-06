<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use App\Services\ShiftReconciliationService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Abrir Nuevo Turno Diario')
                ->slideOver()
                ->modalWidth('7xl')
                ->after(function (Shift $record): void {
                    ShiftReconciliationService::ensureShiftItemsInitialized($record);
                    ShiftReconciliationService::reconcile($record);
                }),
        ];
    }
}

