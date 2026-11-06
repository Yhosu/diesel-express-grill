<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShiftTemplates\Pages;

use App\Filament\Resources\ShiftTemplates\ShiftTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShiftTemplate extends EditRecord
{
    protected static string $resource = ShiftTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

