<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use App\Filament\Concerns\InteractsWithUserRoles;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\Supply;
use App\Services\CostingService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    use InteractsWithUserRoles;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = '2. Costos, Recetas BOM y Compras';

    protected static ?string $navigationLabel = 'Productos y Recetas BOM';

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Maestro de Productos y Costos (BOM)';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Maestro del Producto y Estructura de Costo')
                    ->description('El Costo Maestro Unitario se calcula automáticamente desde los componentes de la receta BOM + Mano de Obra + Otros Costos Variables.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('sku')
                                ->label('SKU')
                                ->placeholder('DN01')
                                ->required()
                                ->maxLength(20)
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->label('Producto')
                                ->required()
                                ->maxLength(120),

                            TextInput::make('selling_price')
                                ->label('Precio de Venta (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->required(),

                            TextInput::make('direct_labor_cost')
                                ->label('Mano de Obra x Unidad (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->default(1.50)
                                ->required(),

                            TextInput::make('other_variable_cost')
                                ->label('Otros Costos Variables (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->default(0.50)
                                ->required(),

                            TextInput::make('portion_control')
                                ->label('Regla de Control de Porción')
                                ->placeholder('Ej: Balanza 120g carne y dosificador 20g salsa'),
                        ]),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('ingredients_cost')
                                ->label('Ingredientes + Bebidas')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('packaging_cost')
                                ->label('Empaques')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('direct_bom_cost')
                                ->label('Costo Directo BOM')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('master_unit_cost')
                                ->label('Costo Maestro Unitario')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('contribution_amount')
                                ->label('Contribución Unitaria')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),
                        ]),

                        Toggle::make('is_active')
                            ->label('Producto Activo para Despacho Móvil')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Receta Estándar y Lista de Materiales (BOM)')
                    ->description('Ingredientes, empaques, consumibles (servilletas, guantes) y gas proporcional por cada unidad vendida.')
                    ->schema([
                        Repeater::make('boms')
                            ->label('Componentes de la Receta')
                            ->relationship('boms')
                            ->defaultItems(1)
                            ->addActionLabel('Agregar Insumo / Empaque a la Receta')
                            ->schema([
                                Select::make('supply_id')
                                    ->label('Insumo / Empaque')
                                    ->options(fn (): array => Supply::query()
                                        ->orderBy('code')
                                        ->get()
                                        ->mapWithKeys(fn (Supply $s): array => [
                                            $s->id => "{$s->code} - {$s->name} ({$s->base_unit} @ Bs." . number_format((float) $s->adjusted_unit_cost, 4) . ')',
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                        $supply = Supply::query()->find($state);
                                        if ($supply) {
                                            $unitCost = (float) $supply->adjusted_unit_cost;
                                            $qty = (float) ($get('qty_per_unit') ?? 1);
                                            $set('base_unit_cost_snapshot', round($unitCost, 6));
                                            $set('component_cost', round($qty * $unitCost, 4));
                                        }
                                    })
                                    ->columnSpanFull(),

                                Grid::make(['default' => 1, 'sm' => 3])->schema([
                                    TextInput::make('qty_per_unit')
                                        ->label('Cantidad x Unidad')
                                        ->numeric()
                                        ->step('0.01')
                                        ->default(1)
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                            $supply = Supply::query()->find($get('supply_id'));
                                            $unitCost = $supply ? (float) $supply->adjusted_unit_cost : (float) ($get('base_unit_cost_snapshot') ?? 0);
                                            $set('base_unit_cost_snapshot', round($unitCost, 6));
                                            $set('component_cost', round(((float) $state) * $unitCost, 4));
                                        }),

                                    TextInput::make('base_unit_cost_snapshot')
                                        ->label('Costo Unit. Ajustado')
                                        ->numeric()
                                        ->prefix('Bs.')
                                        ->readOnly()
                                        ->dehydrated(),

                                    TextInput::make('component_cost')
                                        ->label('Costo Componente')
                                        ->numeric()
                                        ->prefix('Bs.')
                                        ->readOnly()
                                        ->dehydrated(),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sku')
            ->columns([
                Split::make([
                    TextColumn::make('sku')
                        ->label('SKU')
                        ->badge()
                        ->color('primary')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable()
                        ->grow(false),

                    TextColumn::make('name')
                        ->label('Producto')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable(),

                    TextColumn::make('selling_price')
                        ->formatStateUsing(fn ($state): string => 'Venta: Bs. ' . number_format((float) $state, 2))
                        ->sortable(),

                    TextColumn::make('master_unit_cost')
                        ->formatStateUsing(fn ($state): string => 'Costo BOM: Bs. ' . number_format((float) $state, 2))
                        ->weight(FontWeight::Bold)
                        ->color('warning')
                        ->sortable(),

                    TextColumn::make('contribution_margin_pct')
                        ->formatStateUsing(fn ($state): string => 'Margen: ' . number_format(((float) $state) * 100, 1) . '%')
                        ->badge()
                        ->color(fn ($state): string => ((float) $state) >= 0.50 ? 'success' : (((float) $state) >= 0.30 ? 'warning' : 'danger'))
                        ->grow(false),

                    IconColumn::make('is_active')
                        ->label('Activo')
                        ->boolean()
                        ->grow(false),
                ]),

                Panel::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('ingredients_cost')
                                ->formatStateUsing(fn ($state): string => 'Costo Ingredientes y Bebidas: Bs. ' . number_format((float) $state, 2)),
                            TextColumn::make('packaging_cost')
                                ->formatStateUsing(fn ($state): string => 'Costo Empaques: Bs. ' . number_format((float) $state, 2)),
                        ]),

                        Stack::make([
                            TextColumn::make('direct_bom_cost')
                                ->formatStateUsing(fn ($state): string => 'Costo Directo Receta BOM: Bs. ' . number_format((float) $state, 2)),
                            TextColumn::make('contribution_amount')
                                ->formatStateUsing(fn ($state): string => 'Contribución Unitaria: Bs. ' . number_format((float) $state, 2))
                                ->weight(FontWeight::Bold)
                                ->color('success'),
                        ]),

                        Stack::make([
                            TextColumn::make('boms_count')
                                ->counts('boms')
                                ->formatStateUsing(fn ($state): string => 'Componentes en Receta BOM: ' . (int) $state . ' insumos'),
                            TextColumn::make('portion_control')
                                ->formatStateUsing(fn ($state): string => 'Control de Porción: ' . ($state ?: '-')),
                        ]),
                    ]),
                ])->collapsible(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver Receta')
                    ->slideOver()
                    ->modalWidth('6xl'),
                EditAction::make()
                    ->label('Receta / Costo')
                    ->slideOver()
                    ->modalWidth('6xl')
                    ->after(function (Product $record): void {
                        CostingService::recalculateProduct($record);
                    })
                    ->visible(fn (): bool => self::currentAuthUser()?->isSuperAdmin() ?? false),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->slideOver()
                    ->visible(fn (): bool => self::currentAuthUser()?->isSuperAdmin() ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
        ];
    }
}

