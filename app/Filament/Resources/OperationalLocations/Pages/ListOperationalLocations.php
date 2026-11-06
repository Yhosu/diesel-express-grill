<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationalLocations\Pages;

use App\Filament\Resources\OperationalLocations\OperationalLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationalLocations extends ListRecords
{
    protected static string $resource = OperationalLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Ubicación Operativa')
                ->slideOver()
                ->modalWidth('3xl'),
        ];
    }
}

