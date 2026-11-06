<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ShiftAuditTableWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Auditoría de Turnos, Conciliación de Caja/QR y Semáforo de Control';

    public function table(Table $table): Table
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;
        $location = $this->pageFilters['location'] ?? null;
        $shiftId = $this->pageFilters['shift_id'] ?? null;

        return $table
            ->query(
                Shift::query()
                    ->when($startDate, fn (Builder $q) => $q->whereDate('shift_date', '>=', $startDate))
                    ->when($endDate, fn (Builder $q) => $q->whereDate('shift_date', '<=', $endDate))
                    ->when($location, fn (Builder $q) => $q->where('location', $location))
                    ->when($shiftId, fn (Builder $q) => $q->where('id', $shiftId))
                    ->orderByDesc('shift_date')
            )
            ->paginated(false)
            ->columns([
                Split::make([
                    TextColumn::make('shift_code')
                        ->badge()
                        ->color('primary')
                        ->grow(false),

                    Stack::make([
                        TextColumn::make('location')
                            ->weight(FontWeight::Bold),
                        TextColumn::make('shift_date')
                            ->date('d/m/Y')
                            ->size('xs')
                            ->color('gray'),
                    ]),

                    TextColumn::make('operator_name')
                        ->description(fn (Shift $record): string => 'Unidad: ' . ($record->unit_code ?? 'DN-M01')),

                    TextColumn::make('gross_contribution')
                        ->formatStateUsing(fn ($state): string => 'Contrib.: Bs. ' . number_format((float) $state, 2))
                        ->weight(FontWeight::Bold)
                        ->color('success'),

                    TextColumn::make('total_semaphore')
                        ->badge()
                        ->grow(false),
                ]),

                Panel::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('expected_cash_sales')
                                ->formatStateUsing(fn ($state): string => 'Ventas Efectivo POS: Bs. ' . number_format((float) $state, 2)),
                            TextColumn::make('expected_qr_sales')
                                ->formatStateUsing(fn ($state): string => 'Ventas QR POS: Bs. ' . number_format((float) $state, 2)),
                        ]),

                        Stack::make([
                            TextColumn::make('cash_variance')
                                ->formatStateUsing(fn ($state): string => 'Diferencia Efectivo: Bs. ' . number_format((float) $state, 2))
                                ->color(fn (mixed $state): string => abs((float) $state) < 0.01 ? 'success' : 'danger'),
                            TextColumn::make('qr_variance')
                                ->formatStateUsing(fn ($state): string => 'Diferencia QR: Bs. ' . number_format((float) $state, 2))
                                ->color(fn (mixed $state): string => abs((float) $state) < 0.01 ? 'success' : 'danger'),
                            TextColumn::make('inventory_variance_value')
                                ->formatStateUsing(fn ($state): string => 'Diferencia Inventario: Bs. ' . number_format((float) $state, 2))
                                ->color(fn (mixed $state): string => abs((float) $state) < 0.01 ? 'success' : 'danger'),
                        ]),
                    ]),
                ])->collapsible(),
            ])
            ->recordActions([
                Action::make('open_shift')
                    ->label(fn (): string => (auth()->user()?->isSuperAdmin() ?? false) ? 'Auditar Turno' : 'Ver Turno')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Shift $record): string => ShiftResource::getUrl('index', ['tableSearch' => $record->shift_code])),
            ]);
    }
}

