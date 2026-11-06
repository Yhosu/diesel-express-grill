<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShiftTemplates\Pages;

use App\Filament\Resources\ShiftTemplates\ShiftTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShiftTemplate extends CreateRecord
{
    protected static string $resource = ShiftTemplateResource::class;
}

