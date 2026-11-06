<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationalLocations\Pages;

use App\Filament\Resources\OperationalLocations\OperationalLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationalLocation extends EditRecord
{
    protected static string $resource = OperationalLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

