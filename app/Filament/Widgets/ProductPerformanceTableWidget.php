<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Shift;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ProductPerformanceTableWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Análisis de Rentabilidad, Costo Maestro BOM y Movimiento por Producto (10 SKUs)';

    public function table(Table $table): Table
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;
        $location = $this->pageFilters['location'] ?? null;
        $shiftId = $this->pageFilters['shift_id'] ?? null;

        $shiftIds = Shift::query()
            ->when($startDate, fn (Builder $q) => $q->whereDate('shift_date', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate('shift_date', '<=', $endDate))
            ->when($location, fn (Builder $q) => $q->where('location', $location))
            ->when($shiftId, fn (Builder $q) => $q->where('id', $shiftId))
            ->pluck('id')
            ->all();

        return $table
            ->query(
                Product::query()
                    ->withSum([
                        'shiftItems as period_dispatched_qty' => fn (Builder $q) => $q->whereIn('shift_id', $shiftIds),
                    ], 'available_qty')
                    ->withSum([
                        'shiftItems as period_sold_qty' => fn (Builder $q) => $q->whereIn('shift_id', $shiftIds),
                    ], 'sold_qty')
                    ->withSum([
                        'shiftItems as period_waste_qty' => fn (Builder $q) => $q->whereIn('shift_id', $shiftIds),
                    ], 'waste_qty')
                    ->withSum([
                        'shiftItems as period_variance_qty' => fn (Builder $q) => $q->whereIn('shift_id', $shiftIds),
                    ], 'variance_qty')
                    ->withSum([
                        'sales as period_sales_value' => fn (Builder $q) => $q->whereIn('shift_id', $shiftIds),
                    ], 'sales_value')
                    ->withSum([
                        'sales as period_contribution_value' => fn (Builder $q) => $q->whereIn('shift_id', $shiftIds),
                    ], 'contribution_value')
                    ->orderBy('sku')
            )
            ->paginated(false)
            ->columns([
                Split::make([
                    TextColumn::make('sku')
                        ->badge()
                        ->color('primary')
                        ->grow(false),

                    TextColumn::make('name')
                        ->weight(FontWeight::Bold),

                    TextColumn::make('selling_price')
                        ->formatStateUsing(fn ($state): string => 'Precio: Bs. ' . number_format((float) $state, 2)),

                    TextColumn::make('period_sold_qty')
                        ->formatStateUsing(fn ($state): string => 'Vendidas: ' . (int) ($state ?? 0) . ' uds.')
                        ->weight(FontWeight::Bold)
                        ->color('success'),

                    TextColumn::make('period_sales_value')
                        ->formatStateUsing(fn ($state): string => 'Ventas: Bs. ' . number_format((float) ($state ?? 0), 2))
                        ->weight(FontWeight::Bold)
                        ->color('success'),

                    TextColumn::make('contribution_margin_pct')
                        ->formatStateUsing(fn (mixed $state): string => 'Margen: ' . number_format((float) $state * 100, 1) . '%')
                        ->badge()
                        ->color(fn (mixed $state): string => ((float) $state >= 0.55) ? 'success' : 'warning')
                        ->grow(false),
                ]),

                Panel::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('master_unit_cost')
                                ->formatStateUsing(fn ($state): string => 'Costo Maestro Unitario BOM: Bs. ' . number_format((float) $state, 2)),
                            TextColumn::make('period_dispatched_qty')
                                ->formatStateUsing(fn ($state): string => 'Unidades Despachadas: ' . (int) ($state ?? 0) . ' uds.'),
                        ]),

                        Stack::make([
                            TextColumn::make('period_waste_qty')
                                ->formatStateUsing(fn ($state): string => 'Mermas en Período: ' . (int) ($state ?? 0) . ' uds.'),
                            TextColumn::make('period_variance_qty')
                                ->formatStateUsing(fn ($state): string => 'Diferencia Física: ' . (int) ($state ?? 0) . ' uds.'),
                        ]),

                        Stack::make([
                            TextColumn::make('period_cogs_value')
                                ->state(fn (Product $record): float => round((float) ($record->period_sales_value ?? 0) - (float) ($record->period_contribution_value ?? 0), 2))
                                ->formatStateUsing(fn ($state): string => 'Costo de Ventas Período: Bs. ' . number_format((float) $state, 2)),
                            TextColumn::make('period_contribution_value')
                                ->formatStateUsing(fn ($state): string => 'Contribución Total Período: Bs. ' . number_format((float) ($state ?? 0), 2))
                                ->weight(FontWeight::Bold)
                                ->color('info'),
                        ]),
                    ]),
                ])->collapsible(),
            ]);
    }
}

