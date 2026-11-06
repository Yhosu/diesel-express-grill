<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationalLocations\Pages;

use App\Filament\Resources\OperationalLocations\OperationalLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationalLocation extends CreateRecord
{
    protected static string $resource = OperationalLocationResource::class;
}

