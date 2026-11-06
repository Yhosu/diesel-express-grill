<?php

declare(strict_types=1);

namespace App\Filament\Resources\Exceptions\Pages;

use App\Filament\Resources\Exceptions\ExceptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExceptions extends ListRecords
{
    protected static string $resource = ExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar Merma / Excepción')
                ->slideOver()
                ->modalWidth('4xl'),
        ];
    }
}

