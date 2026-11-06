<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Filament\Concerns\InteractsWithUserRoles;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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

class ExpenseResource extends Resource
{
    use InteractsWithUserRoles;

    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = '2. Costos, Recetas BOM y Compras';

    protected static ?string $navigationLabel = 'Egresos Operativos';

    protected static ?string $modelLabel = 'Egreso Operativo';

    protected static ?string $pluralModelLabel = 'Egresos Operativos fuera de Receta';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Registro de Egreso Operativo No Incluido en Recetas')
                    ->description('Combustible, permisos, alquiler del espacio, mantenimiento, transporte, marketing, servicios, limpieza adicional o personal fijo.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('expense_code')
                                ->label('ID Egreso')
                                ->default(fn (): string => 'EGR-' . str_pad((string) (Expense::query()->count() + 1), 3, '0', STR_PAD_LEFT))
                                ->required()
                                ->maxLength(30)
                                ->unique(ignoreRecord: true),

                            DatePicker::make('expense_date')
                                ->label('Fecha del Egreso')
                                ->default(now())
                                ->required(),
                        ]),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            Select::make('shift_id')
                                ->label('Turno Relacionado (Opcional)')
                                ->relationship('shift', 'shift_code')
                                ->searchable()
                                ->preload(),

                            Select::make('category')
                                ->label('Categoría de Egreso')
                                ->options(ExpenseCategory::class)
                                ->required(),
                        ]),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('unit_code')
                                ->label('Unidad Móvil')
                                ->default('DN-M01')
                                ->required(),

                            TextInput::make('location')
                                ->label('Ubicación')
                                ->placeholder('Sopocachi, Centro, San Miguel...'),

                            TextInput::make('supplier')
                                ->label('Proveedor / Beneficiario')
                                ->maxLength(120),

                            TextInput::make('document_ref')
                                ->label('Documento / Recibo')
                                ->maxLength(60),
                        ]),

                        TextInput::make('description')
                            ->label('Descripción del Gasto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('amount')
                                ->label('Monto (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->required(),

                            Select::make('payment_method')
                                ->label('Método de Pago')
                                ->options(PaymentMethod::class)
                                ->default(PaymentMethod::EFECTIVO->value)
                                ->required(),

                            TextInput::make('responsible')
                                ->label('Responsable')
                                ->default('Administrador')
                                ->required(),

                            Textarea::make('notes')
                                ->label('Observaciones')
                                ->rows(2),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expense_date', 'desc')
            ->columns([
                Split::make([
                    TextColumn::make('expense_code')
                        ->badge()
                        ->color('primary')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable()
                        ->grow(false),

                    Stack::make([
                        TextColumn::make('description')
                            ->weight(FontWeight::Bold)
                            ->searchable(),
                        TextColumn::make('category')
                            ->badge(),
                    ]),

                    Stack::make([
                        TextColumn::make('location')
                            ->placeholder('-'),
                        TextColumn::make('expense_date')
                            ->date('d/m/Y')
                            ->size('xs')
                            ->color('gray')
                            ->sortable(),
                    ]),

                    TextColumn::make('payment_method')
                        ->badge()
                        ->grow(false),

                    TextColumn::make('amount')
                        ->formatStateUsing(fn ($state): string => 'Bs. ' . number_format((float) $state, 2))
                        ->weight(FontWeight::Bold)
                        ->color('danger')
                        ->sortable()
                        ->grow(false),
                ]),

                Panel::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('unit_code')
                                ->formatStateUsing(fn ($state): string => 'Unidad Móvil: ' . ($state ?: 'DN-M01')),
                            TextColumn::make('shift.shift_code')
                                ->formatStateUsing(fn ($state): string => 'Jornada Asociada: ' . ($state ?: '-')),
                        ]),

                        Stack::make([
                            TextColumn::make('supplier')
                                ->formatStateUsing(fn ($state): string => 'Proveedor / Beneficiario: ' . ($state ?: '-')),
                            TextColumn::make('document_ref')
                                ->formatStateUsing(fn ($state): string => 'Documento / Recibo: ' . ($state ?: '-')),
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
                            ->when($data['desde'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('expense_date', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('expense_date', '<=', $date));
                    }),

                SelectFilter::make('category')
                    ->label('Categoría')
                    ->options(ExpenseCategory::class),
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
            'index' => ListExpenses::route('/'),
        ];
    }
}

