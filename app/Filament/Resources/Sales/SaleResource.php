<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sales;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\InteractsWithUserRoles;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SaleResource extends Resource
{
    use InteractsWithUserRoles;

    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = '1. Operación Diaria Móvil';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Venta POS';

    protected static ?string $pluralModelLabel = 'Ventas POS (Histórico)';

    protected static ?string $recordTitleAttribute = 'transaction_code';

    public static function form(Schema $schema): Schema
    {
        $recalcTotals = function (Get $get, Set $set): void {
            $qty = max(1, (int) ($get('qty') ?? 1));
            $price = (float) ($get('unit_price') ?? 0);
            $cost = (float) ($get('unit_cost') ?? 0);
            $set('sales_value', number_format(round($qty * $price, 2), 2, '.', ''));
            $set('contribution_value', number_format(round($qty * ($price - $cost), 2), 2, '.', ''));
        };

        return $schema
            ->columns(1)
            ->components([
                Section::make('1. Datos de Operación, Turno y Fecha de Registro')
                    ->description('La fecha y hora actuales se asignan automáticamente al momento del registro. Solo el Super Administrador puede modificar la fecha u hora.')
                    ->icon('heroicon-m-clock')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('transaction_code')
                                ->label('Código de Transacción POS')
                                ->prefixIcon('heroicon-m-hashtag')
                                ->default(fn (): string => 'T' . str_pad((string) (Sale::query()->count() + 1), 3, '0', STR_PAD_LEFT))
                                ->required()
                                ->maxLength(30),

                            Select::make('shift_id')
                                ->label('Jornada / Turno Móvil Activo')
                                ->prefixIcon('heroicon-m-truck')
                                ->options(fn (): array => Shift::query()
                                    ->orderByDesc('shift_date')
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (Shift $s): array => [
                                        $s->id => "{$s->shift_code} — {$s->location} ({$s->shift_date?->format('d/m/Y')})",
                                    ])
                                    ->all())
                                ->default(fn (): ?int => Shift::query()->orderByDesc('shift_date')->orderByDesc('id')->value('id'))
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            DatePicker::make('sale_date')
                                ->label('Fecha de Registro POS')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->default(fn (): string => now()->toDateString())
                                ->disabled(fn (): bool => ! (self::currentAuthUser()?->isSuperAdmin() ?? false))
                                ->dehydrated()
                                ->helperText(fn (): string => (self::currentAuthUser()?->isSuperAdmin() ?? false)
                                    ? 'Fecha actual por defecto (editable por Super Administrador).'
                                    : 'Asignada automáticamente al día actual (bloqueado para Subadministrador).')
                                ->required(),

                            TimePicker::make('sale_time')
                                ->label('Hora Exacta de Venta')
                                ->prefixIcon('heroicon-m-clock')
                                ->default(fn (): string => now()->format('H:i:s'))
                                ->disabled(fn (): bool => ! (self::currentAuthUser()?->isSuperAdmin() ?? false))
                                ->dehydrated()
                                ->helperText('Hora actual del sistema al momento de emitir la venta.')
                                ->required(),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('2. Selección de Producto, Método de Pago e Importe Total')
                    ->description('Seleccione el producto del menú móvil e indique las unidades vendidas para calcular el total a cobrar.')
                    ->icon('heroicon-m-shopping-bag')
                    ->schema([
                        Select::make('product_id')
                            ->label('Producto del Menú (SKU)')
                            ->prefixIcon('heroicon-m-fire')
                            ->options(fn (): array => Product::query()
                                ->where('is_active', true)
                                ->orderBy('sku')
                                ->get()
                                ->mapWithKeys(fn (Product $p): array => [
                                    $p->id => "{$p->sku} — {$p->name} (Bs. " . number_format((float) $p->selling_price, 2) . ')',
                                ])
                                ->all())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($recalcTotals): void {
                                if (! $state) {
                                    return;
                                }
                                $product = Product::query()->find($state);
                                if ($product) {
                                    $set('unit_price', number_format((float) $product->selling_price, 2, '.', ''));
                                    $set('unit_cost', number_format((float) $product->master_unit_cost, 4, '.', ''));
                                    $recalcTotals($get, $set);
                                }
                            })
                            ->columnSpanFull(),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('qty')
                                ->label('Cantidad Vendida (Uds.)')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $recalcTotals($get, $set)),

                            Select::make('payment_method')
                                ->label('Método de Cobro')
                                ->prefixIcon('heroicon-m-qr-code')
                                ->options(PaymentMethod::posOptions())
                                ->default(PaymentMethod::QR->value)
                                ->required(),
                        ]),

                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make('unit_price')
                                ->label('Precio Unitario (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->step('0.01')
                                ->readOnly(fn (): bool => ! (self::currentAuthUser()?->isSuperAdmin() ?? false))
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $recalcTotals($get, $set)),

                            TextInput::make('sales_value')
                                ->label('TOTAL A COBRAR (Bs.)')
                                ->prefix('Bs.')
                                ->default('0.00')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('unit_cost')
                                ->label('Costo Receta BOM (Bs.)')
                                ->prefix('Bs.')
                                ->readOnly(),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['shift', 'product']))
            ->defaultSort('sale_date', 'desc')
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('transaction_code')
                            ->label('ID Trans.')
                            ->badge()
                            ->color('primary')
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('shift.shift_code')
                            ->label('Jornada')
                            ->size('xs')
                            ->color('gray'),
                    ]),

                    Stack::make([
                        TextColumn::make('product.name')
                            ->label('Producto')
                            ->weight(FontWeight::Bold)
                            ->searchable(),
                        TextColumn::make('product.sku')
                            ->label('SKU')
                            ->badge()
                            ->color('gray'),
                    ]),

                    Stack::make([
                        TextColumn::make('sale_date')
                            ->label('Fecha')
                            ->date('d/m/Y')
                            ->sortable(),
                        TextColumn::make('sale_time')
                            ->label('Hora')
                            ->time('H:i')
                            ->size('xs')
                            ->color('gray'),
                    ]),

                    TextColumn::make('qty')
                        ->formatStateUsing(fn ($state): string => (int) $state . ' uds.')
                        ->weight(FontWeight::Bold)
                        ->badge()
                        ->color('info')
                        ->grow(false),

                    TextColumn::make('payment_method')
                        ->label('Pago')
                        ->badge()
                        ->grow(false),

                    TextColumn::make('sales_value')
                        ->label('Venta Total')
                        ->formatStateUsing(fn ($state): string => 'Bs. ' . number_format((float) $state, 2))
                        ->weight(FontWeight::Bold)
                        ->color('success')
                        ->sortable()
                        ->grow(false),
                ]),

                Panel::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('shift.location')
                                ->formatStateUsing(fn ($state): string => 'Ubicación Móvil: ' . ($state ?: '-')),
                            TextColumn::make('unit_price')
                                ->formatStateUsing(fn ($state): string => 'Precio Venta Unitario: Bs. ' . number_format((float) $state, 2)),
                        ]),

                        Stack::make([
                            TextColumn::make('unit_cost')
                                ->formatStateUsing(fn ($state): string => 'Costo Unitario Receta BOM: Bs. ' . number_format((float) $state, 2)),
                            TextColumn::make('contribution_value')
                                ->formatStateUsing(fn ($state): string => 'Contribución Bruta Generada: Bs. ' . number_format((float) $state, 2))
                                ->weight(FontWeight::Bold)
                                ->color('primary'),
                        ]),
                    ]),
                ])->collapsible(),
            ])
            ->filters([
                Filter::make('sale_date')
                    ->label('Rango de Fechas')
                    ->schema([
                        DatePicker::make('desde')->label('Desde fecha'),
                        DatePicker::make('hasta')->label('Hasta fecha'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('sale_date', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('sale_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('shift_id')
                    ->label('Jornada / Turno')
                    ->relationship('shift', 'shift_code'),

                SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name'),

                SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options(PaymentMethod::posOptions()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->slideOver()
                    ->modalWidth('4xl'),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->visible(fn (): bool => self::currentAuthUser()?->isSuperAdmin() ?? false),
                DeleteAction::make()
                    ->slideOver()
                    ->visible(fn (): bool => self::currentAuthUser()?->isSuperAdmin() ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => self::currentAuthUser()?->isSuperAdmin() ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
        ];
    }
}
