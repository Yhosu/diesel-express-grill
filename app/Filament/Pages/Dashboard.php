<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Shift;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashboard de Control y Reportes por Fecha';

    protected static ?string $navigationLabel = 'Dashboard y Reportes';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filtros Globales de Reporte (Fechas, Ubicación y Turno)')
                    ->description('Filtra en tiempo real todos los indicadores financieros, costos BOM, mermas y conciliaciones por rango de fechas o punto de venta móvil.')
                    ->columns(4)
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Fecha Desde')
                            ->placeholder('Todas las fechas')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),

                        DatePicker::make('endDate')
                            ->label('Fecha Hasta')
                            ->placeholder('Todas las fechas')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),

                        Select::make('location')
                            ->label('Ubicación / Punto Móvil')
                            ->placeholder('Todas las ubicaciones')
                            ->options(fn (): array => Shift::query()
                                ->select('location')
                                ->distinct()
                                ->orderBy('location')
                                ->pluck('location', 'location')
                                ->all()),

                        Select::make('shift_id')
                            ->label('Turno Específico')
                            ->placeholder('Todos los turnos')
                            ->options(fn (): array => Shift::query()
                                ->orderByDesc('shift_date')
                                ->get()
                                ->mapWithKeys(fn (Shift $s): array => [
                                    $s->id => "{$s->shift_code} ({$s->shift_date->format('d/m/Y')} - {$s->location})",
                                ])
                                ->all()),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}

