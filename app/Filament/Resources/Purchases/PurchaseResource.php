<?php

declare(strict_types=1);

namespace App\Filament\Resources\Purchases;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\InteractsWithUserRoles;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Models\Purchase;
use App\Models\Supply;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
use UnitEnum;

class PurchaseResource extends Resource
{
    use InteractsWithUserRoles;

    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = '2. Costos, Recetas BOM y Compras';

    protected static ?string $navigationLabel = 'Registro de Compras';

    protected static ?string $modelLabel = 'Compra de Insumo';

    protected static ?string $pluralModelLabel = 'Registro de Compras de Insumos';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $recalc = function (Get $get, Set $set): void {
            $qty = (float) ($get('qty') ?? 0);
            $price = (float) ($get('unit_price') ?? 0);
            $discount = (float) ($get('discount') ?? 0);
            $freight = (float) ($get('freight') ?? 0);
            $tax = (float) ($get('non_recoverable_tax') ?? 0);

            $subtotal = round($qty * $price, 2);
            $total = round($subtotal - $discount + $freight + $tax, 2);

            $set('subtotal', $subtotal);
            $set('total_cost', $total);
        };

        return $schema
            ->columns(1)
            ->components([
                Section::make('Registro de Compra de Ingredientes, Empaques y Otros Insumos')
                    ->description('Fórmula: Costo Total Compra = Subtotal − Descuento + Transporte/Flete + Impuestos no recuperables.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('purchase_code')
                                ->label('ID Compra')
                                ->default(fn (): string => 'C-' . str_pad((string) (Purchase::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                                ->required()
                                ->maxLength(30)
                                ->unique(ignoreRecord: true),

                            DatePicker::make('purchase_date')
                                ->label('Fecha de Compra')
                                ->default(now())
                                ->required(),

                            TextInput::make('supplier')
                                ->label('Proveedor')
                                ->required()
                                ->maxLength(120),

                            TextInput::make('invoice_number')
                                ->label('Nro. Factura / Recibo')
                                ->placeholder('F-001')
                                ->maxLength(60),
                        ]),

                        Select::make('supply_id')
                            ->label('Insumo / Empaque')
                            ->relationship('supply', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Supply $record): string => "{$record->code} - {$record->name} ({$record->purchase_unit})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) use ($recalc): void {
                                $supply = Supply::query()->find($state);
                                if ($supply) {
                                    $set('purchase_unit', $supply->purchase_unit);
                                    $set('unit_price', (float) $supply->last_purchase_price);
                                    if (empty($get('supplier')) && $supply->main_supplier) {
                                        $set('supplier', $supply->main_supplier);
                                    }
                                    $recalc($get, $set);
                                }
                            })
                            ->columnSpanFull(),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('qty')
                                ->label('Cantidad Comprada')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated($recalc),

                            TextInput::make('purchase_unit')
                                ->label('Presentación Compra')
                                ->readOnly()
                                ->dehydrated(),

                            TextInput::make('unit_price')
                                ->label('Precio Unitario (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated($recalc),

                            TextInput::make('subtotal')
                                ->label('Subtotal (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(),

                            TextInput::make('discount')
                                ->label('Descuento (- Bs.)')
                                ->numeric()
                                ->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated($recalc),

                            TextInput::make('freight')
                                ->label('Transporte / Flete (+ Bs.)')
                                ->numeric()
                                ->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated($recalc),

                            TextInput::make('non_recoverable_tax')
                                ->label('Imp. No Recup. (+ Bs.)')
                                ->numeric()
                                ->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated($recalc),

                            TextInput::make('total_cost')
                                ->label('Costo Total Compra (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(),

                            Select::make('payment_method')
                                ->label('Método de Pago')
                                ->options(PaymentMethod::class)
                                ->default(PaymentMethod::EFECTIVO->value)
                                ->required(),

                            TextInput::make('responsible')
                                ->label('Responsable')
                                ->default('Administrador')
                                ->required(),
                        ]),

                        Toggle::make('update_supply_cost')
                            ->label('Actualizar automáticamente el "Precio Última Compra" en el Maestro de Insumos y recalcular Costos BOM')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('purchase_date', 'desc')
            ->columns([
                Split::make([
                    TextColumn::make('purchase_code')
                        ->badge()
                        ->color('primary')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable()
                        ->grow(false),

                    Stack::make([
                        TextColumn::make('supply.name')
                            ->weight(FontWeight::Bold)
                            ->searchable(),
                        TextColumn::make('supply.code')
                            ->size('xs')
                            ->color('gray'),
                    ]),

                    Stack::make([
                        TextColumn::make('supplier')
                            ->searchable(),
                        TextColumn::make('purchase_date')
                            ->date('d/m/Y')
                            ->size('xs')
                            ->color('gray')
                            ->sortable(),
                    ]),

                    TextColumn::make('qty')
                        ->formatStateUsing(fn ($state, Purchase $record): string => number_format((float) $state, 0) . ' ' . $record->purchase_unit)
                        ->badge()
                        ->color('info')
                        ->grow(false),

                    TextColumn::make('payment_method')
                        ->badge()
                        ->grow(false),

                    TextColumn::make('total_cost')
                        ->formatStateUsing(fn ($state): string => 'Bs. ' . number_format((float) $state, 2))
                        ->weight(FontWeight::Bold)
                        ->color('primary')
                        ->sortable()
                        ->grow(false),
                ]),

                Panel::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('invoice_number')
                                ->formatStateUsing(fn ($state): string => 'Factura / Recibo: ' . ($state ?: '-')),
                            TextColumn::make('unit_price')
                                ->formatStateUsing(fn ($state): string => 'Precio Unitario: Bs. ' . number_format((float) $state, 2)),
                        ]),

                        Stack::make([
                            TextColumn::make('subtotal')
                                ->formatStateUsing(fn ($state): string => 'Subtotal: Bs. ' . number_format((float) $state, 2)),
                            TextColumn::make('freight')
                                ->formatStateUsing(fn ($state): string => 'Transporte / Flete: Bs. ' . number_format((float) $state, 2)),
                        ]),
                    ]),
                ])->collapsible(),
            ])
            ->filters([
                Filter::make('rango_fechas')
                    ->schema([
                        DatePicker::make('desde')->label('Fecha Desde'),
                        DatePicker::make('hasta')->label('Fecha Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('purchase_date', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('purchase_date', '<=', $date));
                    }),

                SelectFilter::make('supply_id')
                    ->label('Insumo')
                    ->relationship('supply', 'name'),

                SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options(PaymentMethod::class),
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
            'index' => ListPurchases::route('/'),
        ];
    }
}

