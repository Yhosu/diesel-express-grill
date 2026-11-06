<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShiftTemplates\Pages;

use App\Filament\Resources\ShiftTemplates\ShiftTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShiftTemplates extends ListRecords
{
    protected static string $resource = ShiftTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Tipo de Turno')
                ->slideOver()
                ->modalWidth('3xl'),
        ];
    }
}

