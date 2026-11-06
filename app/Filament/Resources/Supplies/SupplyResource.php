<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supplies;

use App\Enums\SupplyCategory;
use App\Filament\Concerns\InteractsWithUserRoles;
use App\Filament\Resources\Supplies\Pages\ListSupplies;
use App\Models\Supply;
use App\Services\CostingService;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class SupplyResource extends Resource
{
    use InteractsWithUserRoles;

    protected static ?string $model = Supply::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = '2. Costos, Recetas BOM y Compras';

    protected static ?string $navigationLabel = 'Maestro de Insumos';

    protected static ?string $modelLabel = 'Insumo';

    protected static ?string $pluralModelLabel = 'Maestro de Insumos y Empaques';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $recalc = function (Get $get, Set $set): void {
            $price = (float) ($get('last_purchase_price') ?? 0);
            $content = (float) ($get('content_per_purchase') ?? 1);
            $waste = (float) ($get('standard_waste_pct') ?? 0);
            $costs = CostingService::computeSupplyCosts($price, $content, $waste);
            $set('base_unit_cost', $costs['base_unit_cost']);
            $set('adjusted_unit_cost', $costs['adjusted_unit_cost']);
        };

        return $schema
            ->columns(1)
            ->components([
                Section::make('Datos del Insumo, Empaque o Consumible')
                    ->description('Al actualizar el precio de compra o porcentaje de merma, el sistema recalcula automáticamente el Costo Maestro de todos los productos que usan este insumo en su receta BOM.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('code')
                                ->label('Código (SKU Insumo)')
                                ->placeholder('Ej: INS020')
                                ->required()
                                ->maxLength(20)
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->label('Nombre del Insumo / Empaque')
                                ->required()
                                ->maxLength(120),

                            Select::make('category')
                                ->label('Categoría')
                                ->options(SupplyCategory::class)
                                ->required(),

                            TextInput::make('purchase_unit')
                                ->label('Unidad de Compra')
                                ->placeholder('paquete, kg, caja, bidón 5 L...')
                                ->required(),

                            TextInput::make('content_per_purchase')
                                ->label('Contenido por Compra')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated($recalc),

                            Select::make('base_unit')
                                ->label('Unidad Base (Receta)')
                                ->options([
                                    'unidad' => 'unidad',
                                    'gramo' => 'gramo (g)',
                                    'mililitro' => 'mililitro (ml)',
                                    'rebanada' => 'rebanada',
                                ])
                                ->required(),

                            TextInput::make('last_purchase_price')
                                ->label('Precio Última Compra (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated($recalc),

                            TextInput::make('standard_waste_pct')
                                ->label('Merma Estándar (0 a 1, ej: 0.05 = 5%)')
                                ->numeric()
                                ->step('0.01')
                                ->default(0)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated($recalc),

                            TextInput::make('base_unit_cost')
                                ->label('Costo Unidad Base (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(),

                            TextInput::make('adjusted_unit_cost')
                                ->label('Costo Ajustado x Merma (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(),

                            TextInput::make('min_stock')
                                ->label('Stock Mínimo (Unid. Base)')
                                ->numeric()
                                ->default(0),

                            TextInput::make('main_supplier')
                                ->label('Proveedor Principal')
                                ->maxLength(120),
                        ]),

                        Toggle::make('is_active')
                            ->label('Insumo Activo para Compras y Recetas BOM')
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                Split::make([
                    TextColumn::make('code')
                        ->label('Código')
                        ->badge()
                        ->color('primary')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Bold)
                        ->grow(false),

                    TextColumn::make('name')
                        ->label('Insumo / Empaque')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable(),

                    TextColumn::make('category')
                        ->label('Categoría')
                        ->badge()
                        ->sortable()
                        ->grow(false),

                    TextColumn::make('last_purchase_price')
                        ->formatStateUsing(fn ($state, Supply $record): string => 'Compra: Bs. ' . number_format((float) $state, 2) . ' / ' . $record->purchase_unit)
                        ->sortable(),

                    TextColumn::make('adjusted_unit_cost')
                        ->formatStateUsing(fn ($state, Supply $record): string => 'Ajustado: Bs. ' . number_format((float) $state, 4) . ' / ' . $record->base_unit)
                        ->weight(FontWeight::Bold)
                        ->color('primary')
                        ->sortable(),

                    IconColumn::make('is_active')
                        ->label('Activo')
                        ->boolean()
                        ->grow(false),
                ]),

                Panel::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('purchase_unit')
                                ->formatStateUsing(fn (Supply $record): string => 'Presentación: ' . number_format((float) $record->content_per_purchase, 0) . ' ' . $record->base_unit . ' por ' . $record->purchase_unit),
                            TextColumn::make('base_unit_cost')
                                ->formatStateUsing(fn ($state, Supply $record): string => 'Costo Unidad Base: Bs. ' . number_format((float) $state, 4) . ' / ' . $record->base_unit),
                        ]),

                        Stack::make([
                            TextColumn::make('standard_waste_pct')
                                ->formatStateUsing(fn ($state): string => 'Merma Estándar: ' . number_format(((float) $state) * 100, 1) . '%'),
                            TextColumn::make('main_supplier')
                                ->formatStateUsing(fn ($state): string => 'Proveedor Principal: ' . ($state ?: '-')),
                        ]),
                    ]),
                ])->collapsible(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Categoría')
                    ->options(SupplyCategory::class),
                TernaryFilter::make('is_active')
                    ->label('Estado Activo'),
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
            'index' => ListSupplies::route('/'),
        ];
    }
}

