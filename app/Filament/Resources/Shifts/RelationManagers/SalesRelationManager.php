<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts\RelationManagers;

use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    protected static ?string $title = 'Transacciones de Venta (POS)';

    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    public function form(Schema $schema): Schema
    {
        $isSuperAdmin = fn (): bool => Auth::user() instanceof User && Auth::user()->isSuperAdmin();

        $recalcTotals = function (Get $get, Set $set): void {
            $qty = max(1, (int) ($get('qty') ?? 1));
            $price = (float) ($get('unit_price') ?? 0);
            $set('sales_value', number_format(round($qty * $price, 2), 2, '.', ''));
        };

        return $schema
            ->columns(1)
            ->components([
                Section::make('1. Identificación y Fecha/Hora de Emisión')
                    ->icon('heroicon-m-clock')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make('transaction_code')
                                ->label('ID Transacción')
                                ->prefixIcon('heroicon-m-hashtag')
                                ->default(fn (): string => 'T' . str_pad((string) (Sale::query()->count() + 1), 3, '0', STR_PAD_LEFT))
                                ->required()
                                ->maxLength(30),

                            DatePicker::make('sale_date')
                                ->label('Fecha de Registro')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->default(fn (): string => now()->toDateString())
                                ->disabled(fn (): bool => ! $isSuperAdmin())
                                ->dehydrated()
                                ->required(),

                            TimePicker::make('sale_time')
                                ->label('Hora Exacta')
                                ->prefixIcon('heroicon-m-clock')
                                ->default(fn (): string => now()->format('H:i:s'))
                                ->disabled(fn (): bool => ! $isSuperAdmin())
                                ->dehydrated()
                                ->required(),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('2. Detalle del Producto, Cantidad y Cobro POS')
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
                                ->label('Cantidad Vendida')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $recalcTotals($get, $set)),

                            Select::make('payment_method')
                                ->label('Método de Pago')
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
                                ->readOnly(fn (): bool => ! $isSuperAdmin())
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
                                ->label('Costo Unit. BOM (Bs.)')
                                ->prefix('Bs.')
                                ->readOnly(),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_code')
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('transaction_code')
                    ->label('ID Trans.')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sale_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('sale_time')
                    ->label('Hora')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('qty')
                    ->label('Cant.')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Unidades')),

                TextColumn::make('payment_method')
                    ->label('Pago')
                    ->badge(),

                TextColumn::make('unit_price')
                    ->label('Precio Unit.')
                    ->money('BOB', locale: 'es_BO')
                    ->alignEnd(),

                TextColumn::make('sales_value')
                    ->label('Venta Total (Bs.)')
                    ->money('BOB', locale: 'es_BO')
                    ->weight('bold')
                    ->color('success')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('BOB', locale: 'es_BO')->label('Total Ventas')),

                TextColumn::make('contribution_value')
                    ->label('Contribución (Bs.)')
                    ->money('BOB', locale: 'es_BO')
                    ->color('info')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('BOB', locale: 'es_BO')->label('Contribución')),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options(PaymentMethod::posOptions()),
                SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva Venta POS')
                    ->icon('heroicon-o-shopping-cart')
                    ->slideOver()
                    ->modalHeading('Punto de Venta Móvil (POS) — Nuevo Registro')
                    ->modalDescription('Seleccione el producto, cantidad y método de pago.')
                    ->modalWidth('4xl')
                    ->mutateDataUsing(function (array $data): array {
                        $user = Auth::user();
                        $isSuperAdmin = $user instanceof User && $user->isSuperAdmin();

                        $data['sale_date'] = $isSuperAdmin
                            ? ($data['sale_date'] ?? now()->toDateString())
                            : now()->toDateString();
                        $data['sale_time'] = $isSuperAdmin
                            ? ($data['sale_time'] ?? now()->format('H:i:s'))
                            : now()->format('H:i:s');

                        if (empty($data['unit_cost']) && ! empty($data['product_id'])) {
                            $product = Product::query()->find($data['product_id']);
                            $data['unit_cost'] = $product?->master_unit_cost ?? 0;
                            $data['unit_price'] = $data['unit_price'] ?? ($product?->selling_price ?? 0);
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                DeleteAction::make()
                    ->slideOver()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                ]),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
