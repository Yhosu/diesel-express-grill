<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use App\Services\ShiftReconciliationService;
use Filament\Resources\Pages\CreateRecord;

class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;

    protected function afterCreate(): void
    {
        ShiftReconciliationService::ensureShiftItemsInitialized($this->record);
        ShiftReconciliationService::reconcile($this->record);
    }
}

