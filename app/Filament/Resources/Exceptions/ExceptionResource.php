<?php

declare(strict_types=1);

namespace App\Filament\Resources\Exceptions;

use App\Enums\ExceptionCode;
use App\Filament\Concerns\InteractsWithUserRoles;
use App\Filament\Resources\Exceptions\Pages\ListExceptions;
use App\Models\Product;
use App\Models\Shift;
use App\Models\ShiftException;
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

class ExceptionResource extends Resource
{
    use InteractsWithUserRoles;

    protected static ?string $model = ShiftException::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = '1. Operación Diaria Móvil';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Merma / Excepción';

    protected static ?string $pluralModelLabel = 'Mermas y Excepciones (W01..S01)';

    protected static ?string $recordTitleAttribute = 'exception_code';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('1. Datos de la Jornada, Fecha y Tipo de Excepción')
                    ->description('Seleccione la jornada móvil y la categoría autorizada (W01 Deterioro, W02 Error de preparación, W03 Reposición, C01 Cortesía o S01 Personal).')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('exception_code')
                                ->label('ID Excepción')
                                ->prefixIcon('heroicon-m-hashtag')
                                ->default(fn (): string => 'E' . str_pad((string) (ShiftException::query()->count() + 1), 3, '0', STR_PAD_LEFT))
                                ->required()
                                ->maxLength(30),

                            Select::make('shift_id')
                                ->label('Jornada / Turno Móvil')
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
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (! $state) {
                                        return;
                                    }
                                    $shift = Shift::query()->find($state);
                                    if ($shift) {
                                        $set('exception_date', $shift->shift_date->format('Y-m-d'));
                                    }
                                }),
                        ]),

                        Select::make('code')
                            ->label('Código y Categoría de Excepción')
                            ->options(ExceptionCode::class)
                            ->default(ExceptionCode::W02->value)
                            ->required()
                            ->columnSpanFull(),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            DatePicker::make('exception_date')
                                ->label('Fecha de Registro')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->default(now())
                                ->required(),

                            TimePicker::make('exception_time')
                                ->label('Hora Exacta')
                                ->prefixIcon('heroicon-m-clock')
                                ->default(fn (): string => now()->format('H:i:s'))
                                ->required(),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('2. Producto Afectado, Cantidad y Justificación')
                    ->description('Indique el producto y las unidades descontadas del inventario del turno.')
                    ->icon('heroicon-m-archive-box-x-mark')
                    ->schema([
                        Select::make('product_id')
                            ->label('Producto del Menú (SKU)')
                            ->prefixIcon('heroicon-m-fire')
                            ->options(fn (): array => Product::query()
                                ->where('is_active', true)
                                ->orderBy('sku')
                                ->get()
                                ->mapWithKeys(fn (Product $p): array => [$p->id => "{$p->sku} — {$p->name} (Bs. " . number_format((float) $p->selling_price, 2) . ')'])
                                ->all())
                            ->required()
                            ->searchable()
                            ->preload()
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
                                ->label('Cantidad Afectada (Unidades)')
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
                            ->label('Motivo / Autorización del Supervisor')
                            ->placeholder('Ej: Error de preparación o comida de personal autorizada')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['shift', 'product']))
            ->defaultSort('exception_date', 'desc')
            ->columns([
                Split::make([
                    TextColumn::make('exception_code')
                        ->badge()
                        ->color('warning')
                        ->searchable()
                        ->sortable()
                        ->grow(false),

                    Stack::make([
                        TextColumn::make('product.name')
                            ->weight(FontWeight::Bold)
                            ->searchable(),
                        TextColumn::make('product.sku')
                            ->size('xs')
                            ->color('gray'),
                    ]),

                    TextColumn::make('code')
                        ->badge()
                        ->grow(false),

                    TextColumn::make('qty')
                        ->formatStateUsing(fn ($state): string => (int) $state . ' uds.')
                        ->weight(FontWeight::Bold)
                        ->badge()
                        ->color('info')
                        ->grow(false),

                    TextColumn::make('retail_value')
                        ->formatStateUsing(fn ($state): string => 'Bs. ' . number_format((float) $state, 2))
                        ->weight(FontWeight::Bold)
                        ->color('danger')
                        ->sortable()
                        ->grow(false),
                ]),

                Panel::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('shift.shift_code')
                                ->formatStateUsing(fn ($state): string => 'Jornada Móvil: ' . ($state ?: '-')),
                            TextColumn::make('exception_date')
                                ->date('d/m/Y')
                                ->description(fn (ShiftException $record): string => 'Hora: ' . substr((string) $record->exception_time, 0, 5)),
                        ]),

                        TextColumn::make('reason')
                            ->formatStateUsing(fn ($state): string => 'Motivo / Autorización: ' . ($state ?: '-')),
                    ]),
                ])->collapsible(),
            ])
            ->filters([
                Filter::make('exception_date')
                    ->label('Rango de Fechas')
                    ->schema([
                        DatePicker::make('desde')->label('Desde fecha'),
                        DatePicker::make('hasta')->label('Hasta fecha'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('exception_date', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('exception_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('code')
                    ->label('Código de Excepción')
                    ->options(ExceptionCode::class),

                SelectFilter::make('shift_id')
                    ->label('Turno')
                    ->relationship('shift', 'shift_code'),

                SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name'),
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
            'index' => ListExceptions::route('/'),
        ];
    }
}

