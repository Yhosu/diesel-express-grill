<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts\RelationManagers;

use App\Enums\ExceptionCode;
use App\Models\Product;
use App\Models\ShiftException;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExceptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'exceptions';

    protected static ?string $title = 'Mermas, Cortesías y Comida de Personal (W01..S01)';

    protected static ?string $modelLabel = 'Excepción';

    protected static ?string $pluralModelLabel = 'Excepciones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Detalle de la Merma, Cortesía o Comida de Personal')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('exception_code')
                                ->label('ID Excepción')
                                ->default(fn (): string => 'E' . str_pad((string) (ShiftException::query()->count() + 1), 3, '0', STR_PAD_LEFT))
                                ->required()
                                ->maxLength(30),

                            TimePicker::make('exception_time')
                                ->label('Hora')
                                ->default(fn (): string => now()->format('H:i:s'))
                                ->required(),
                        ]),

                        Select::make('code')
                            ->label('Tipo / Código de Excepción')
                            ->options(ExceptionCode::class)
                            ->default(ExceptionCode::W02->value)
                            ->required()
                            ->columnSpanFull(),

                        Select::make('product_id')
                            ->label('Producto (SKU)')
                            ->options(fn (): array => Product::query()
                                ->where('is_active', true)
                                ->orderBy('sku')
                                ->get()
                                ->mapWithKeys(fn (Product $p): array => [$p->id => "{$p->sku} — {$p->name} (Bs. {$p->selling_price})"])
                                ->all())
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (! $state) {
                                    return;
                                }
                                $product = Product::query()->find($state);
                                if ($product) {
                                    $set('unit_price', (string) $product->selling_price);
                                }
                            })
                            ->columnSpanFull(),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('qty')
                                ->label('Cantidad (Unidades)')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required(),

                            TextInput::make('unit_price')
                                ->label('Precio Venta Unitario (Bs.)')
                                ->prefix('Bs.')
                                ->numeric()
                                ->step('0.01')
                                ->required(),
                        ]),

                        TextInput::make('reason')
                            ->label('Motivo / Autorización')
                            ->placeholder('Ej: Error de preparación o comida autorizada')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('exception_code')
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('exception_code')
                    ->label('ID Exc.')
                    ->badge()
                    ->color('warning')
                    ->searchable(),

                TextColumn::make('exception_time')
                    ->label('Hora')
                    ->time('H:i'),

                TextColumn::make('code')
                    ->label('Código Excepción')
                    ->badge(),

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

                TextColumn::make('reason')
                    ->label('Motivo / Detalle')
                    ->searchable(),

                TextColumn::make('retail_value')
                    ->label('Valor Retail (Bs.)')
                    ->money('BOB', locale: 'es_BO')
                    ->weight('bold')
                    ->color('danger')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('BOB', locale: 'es_BO')->label('Total Retail')),
            ])
            ->filters([
                SelectFilter::make('code')
                    ->label('Código')
                    ->options(ExceptionCode::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar Excepción')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->mutateDataUsing(function (array $data): array {
                        $shift = $this->getOwnerRecord();
                        $data['exception_date'] = $shift->shift_date;
                        if (empty($data['unit_price']) && ! empty($data['product_id'])) {
                            $product = Product::query()->find($data['product_id']);
                            $data['unit_price'] = $product?->selling_price ?? 0;
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                DeleteAction::make()
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

