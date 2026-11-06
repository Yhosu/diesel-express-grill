<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts;

use App\Enums\ExceptionCode;
use App\Enums\PaymentMethod;
use App\Enums\ReconciliationSemaphore;
use App\Enums\ShiftStatus;
use App\Filament\Concerns\InteractsWithUserRoles;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Filament\Resources\Shifts\RelationManagers\ExceptionsRelationManager;
use App\Filament\Resources\Shifts\RelationManagers\SalesRelationManager;
use App\Models\OperationalLocation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftException;
use App\Models\ShiftTemplate;
use App\Services\ShiftReconciliationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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

class ShiftResource extends Resource
{
    use InteractsWithUserRoles;

    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = '1. Operación Diaria Móvil';

    protected static ?string $navigationLabel = 'Jornadas y Conciliación';

    protected static ?string $modelLabel = 'Jornada / Turno';

    protected static ?string $pluralModelLabel = 'Control de Jornadas: Despacho y Conciliación';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $recalcFinancialPreview = function (Get $get, Set $set): void {
            $startingFloat = (float) ($get('starting_float') ?? 0);
            $countedCash = (float) ($get('counted_cash') ?? 0);
            $confirmedQr = (float) ($get('confirmed_qr') ?? 0);
            $expectedCash = (float) ($get('expected_cash_sales') ?? 0);
            $expectedQr = (float) ($get('expected_qr_sales') ?? 0);

            $actualCashSales = round($countedCash - $startingFloat, 2);
            $cashVariance = round($actualCashSales - $expectedCash, 2);
            $qrVariance = round($confirmedQr - $expectedQr, 2);

            $set('actual_cash_sales', number_format($actualCashSales, 2, '.', ''));
            $set('cash_variance', number_format($cashVariance, 2, '.', ''));
            $set('qr_variance', number_format($qrVariance, 2, '.', ''));
        };

        return $schema
            ->columns(1)
            ->components([
                Section::make('1. Apertura de Turno, Horario Asociado y Custodia')
                    ->description('Seleccione el Turno y la Ubicación configurados en el sistema para cargar automáticamente horarios, unidad móvil y fondo de caja.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('shift_code')
                                ->label('ID de Jornada')
                                ->default(fn (): string => 'DN-M01-' . str_pad((string) (Shift::query()->count() + 1), 3, '0', STR_PAD_LEFT))
                                ->required()
                                ->maxLength(40)
                                ->unique(ignoreRecord: true),

                            DatePicker::make('shift_date')
                                ->label('Fecha de Operación')
                                ->default(now())
                                ->required(),

                            Select::make('shift_template_id')
                                ->label('Turno Asociado (Configuración)')
                                ->options(fn (): array => ShiftTemplate::query()
                                    ->where('is_active', true)
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn (ShiftTemplate $t): array => [
                                        $t->id => "{$t->code} - {$t->name} (" . substr((string) $t->start_time, 0, 5) . ' a ' . substr((string) $t->end_time, 0, 5) . ')',
                                    ])
                                    ->all())
                                ->default(fn (): ?int => ShiftTemplate::query()->where('is_active', true)->orderBy('id')->value('id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (! $state) {
                                        return;
                                    }
                                    $template = ShiftTemplate::query()->find($state);
                                    if ($template) {
                                        $set('start_time', $template->start_time);
                                        $set('close_time', $template->end_time);
                                        $set('starting_float', (string) $template->default_float);
                                    }
                                }),

                            Select::make('operational_location_id')
                                ->label('Ubicación Operativa')
                                ->options(fn (): array => OperationalLocation::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (OperationalLocation $loc): array => [
                                        $loc->id => "{$loc->code} — {$loc->name}",
                                    ])
                                    ->all())
                                ->default(fn (): ?int => OperationalLocation::query()->where('is_active', true)->orderBy('id')->value('id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (! $state) {
                                        return;
                                    }
                                    $loc = OperationalLocation::query()->find($state);
                                    if ($loc) {
                                        $set('location', $loc->name);
                                        $set('unit_code', $loc->default_unit_code);
                                    }
                                }),

                            TextInput::make('location')
                                ->label('Nombre Zona')
                                ->default(fn (): string => (string) (OperationalLocation::query()->where('is_active', true)->orderBy('id')->value('name') ?? 'Sopocachi'))
                                ->readOnly()
                                ->dehydrated()
                                ->required(),

                            TextInput::make('unit_code')
                                ->label('Unidad Móvil')
                                ->default('DN-M01')
                                ->required(),

                            TextInput::make('operator_name')
                                ->label('Operador Responsable')
                                ->required()
                                ->maxLength(120),

                            TextInput::make('starting_float')
                                ->label('Fondo Inicial / Float (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->default(200.00)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $recalcFinancialPreview($get, $set))
                                ->required(),

                            TimePicker::make('start_time')
                                ->label('Hora Apertura')
                                ->default('11:00:00'),

                            TimePicker::make('close_time')
                                ->label('Hora Cierre')
                                ->default('15:30:00'),

                            Select::make('status')
                                ->label('Estado del Turno')
                                ->options(ShiftStatus::class)
                                ->default(ShiftStatus::OPEN->value)
                                ->required(),
                        ]),

                        Toggle::make('operator_accepted_custody')
                            ->label('Declaración de Custodia: El operador confirma la recepción de los 10 productos y del fondo de caja inicial.')
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('2. Cierre Financiero y Conciliación Automática de Caja y QR')
                    ->description('Ingrese el Efectivo Físico Contado al cierre (incluyendo el fondo inicial) y los Pagos QR Confirmados en extracto. El sistema calcula diferencias y semáforo automáticamente.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('counted_cash')
                                ->label('Efectivo Físico Contado al Cierre (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $recalcFinancialPreview($get, $set))
                                ->helperText('Incluye el Fondo Inicial (Ventas Efectivo + Fondo)')
                                ->required(),

                            TextInput::make('confirmed_qr')
                                ->label('Pagos QR Confirmados en Banco (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $recalcFinancialPreview($get, $set))
                                ->helperText('Total verificado en extracto bancario/QR')
                                ->required(),

                            TextInput::make('expected_cash_sales')
                                ->label('Ventas Efectivo POS (Esperado)')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('expected_qr_sales')
                                ->label('Ventas QR POS (Esperado)')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('actual_cash_sales')
                                ->label('Efectivo Real (Contado - Fondo)')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('cash_variance')
                                ->label('Diferencia Efectivo (Bs.)')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('qr_variance')
                                ->label('Diferencia QR (Bs.)')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('inventory_variance_value')
                                ->label('Valor Dif. Inventario (Bs.)')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('gross_contribution')
                                ->label('Contribución Bruta Turno (Bs.)')
                                ->prefix('Bs.')
                                ->readOnly()
                                ->dehydrated(false),
                        ]),

                        Textarea::make('notes')
                            ->label('Observaciones / Justificación de Diferencias o Auditoría')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('3. Control Físico de los 10 Productos: Despacho Inicial y Conteo de Cierre')
                    ->description('Balance por SKU: Inicial + Reposición = Vendido POS + Merma + Cortesía + Personal + Conteo Final.')
                    ->hiddenOn('create')
                    ->schema([
                        Repeater::make('items')
                            ->label('Inventario por SKU del Turno')
                            ->relationship('items')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto (SKU)')
                                    ->options(fn (): array => Product::query()
                                        ->orderBy('sku')
                                        ->get()
                                        ->mapWithKeys(fn (Product $p): array => [$p->id => "{$p->sku} - {$p->name} (Bs.{$p->selling_price})"])
                                        ->all())
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpanFull(),

                                Grid::make(['default' => 2, 'sm' => 4])->schema([
                                    TextInput::make('opening_qty')
                                        ->label('Inicial')
                                        ->numeric()
                                        ->default(0)
                                        ->required(),

                                    TextInput::make('replenish_qty')
                                        ->label('Reposición')
                                        ->numeric()
                                        ->default(0)
                                        ->required(),

                                    TextInput::make('sold_qty')
                                        ->label('Vendido POS')
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated(false),

                                    TextInput::make('waste_qty')
                                        ->label('Merma')
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated(false),

                                    TextInput::make('closing_qty')
                                        ->label('Conteo Final')
                                        ->numeric()
                                        ->default(0)
                                        ->required(),

                                    TextInput::make('variance_qty')
                                        ->label('Dif. Unid.')
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated(false),

                                    TextInput::make('variance_value')
                                        ->label('Dif. (Bs.)')
                                        ->prefix('Bs.')
                                        ->readOnly()
                                        ->dehydrated(false),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['shiftTemplate']))
            ->defaultSort('shift_date', 'desc')
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('shift_code')
                            ->label('ID Turno')
                            ->weight(FontWeight::Bold)
                            ->color('primary')
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('shiftTemplate.name')
                            ->label('Horario')
                            ->placeholder('Turno Estándar')
                            ->size('xs')
                            ->color('gray'),
                    ]),

                    Stack::make([
                        TextColumn::make('location')
                            ->label('Ubicación')
                            ->weight(FontWeight::SemiBold)
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('shift_date')
                            ->label('Fecha')
                            ->date('d/m/Y')
                            ->size('xs')
                            ->color('gray')
                            ->sortable(),
                    ]),

                    Stack::make([
                        TextColumn::make('operator_name')
                            ->label('Operador')
                            ->searchable(),
                        TextColumn::make('unit_code')
                            ->label('Unidad')
                            ->badge()
                            ->color('gray'),
                    ]),

                    Stack::make([
                        TextColumn::make('total_expected_sales')
                            ->label('Ventas POS')
                            ->formatStateUsing(fn ($state): string => 'Ventas: Bs. ' . number_format((float) $state, 2))
                            ->weight(FontWeight::Bold)
                            ->color('success')
                            ->sortable(),
                        TextColumn::make('gross_contribution')
                            ->label('Contribución')
                            ->formatStateUsing(fn ($state): string => 'Contrib.: Bs. ' . number_format((float) $state, 2))
                            ->size('xs')
                            ->color('primary'),
                    ]),

                    TextColumn::make('total_semaphore')
                        ->label('Semáforo')
                        ->badge()
                        ->grow(false),

                    TextColumn::make('status')
                        ->label('Estado')
                        ->badge()
                        ->grow(false),
                ]),

                Panel::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('total_dispatched_qty')
                                ->formatStateUsing(fn ($state): string => 'Despacho Total: ' . (int) $state . ' uds.'),
                            TextColumn::make('total_sold_qty')
                                ->formatStateUsing(fn ($state): string => 'Unidades Vendidas POS: ' . (int) $state . ' uds.')
                                ->weight(FontWeight::Bold),
                            TextColumn::make('total_waste_qty')
                                ->formatStateUsing(fn ($state): string => 'Mermas Registradas: ' . (int) $state . ' uds.'),
                        ]),

                        Stack::make([
                            TextColumn::make('expected_cash_sales')
                                ->formatStateUsing(fn ($state): string => 'Ventas Efectivo POS: Bs. ' . number_format((float) $state, 2)),
                            TextColumn::make('expected_qr_sales')
                                ->formatStateUsing(fn ($state): string => 'Ventas QR POS: Bs. ' . number_format((float) $state, 2)),
                            TextColumn::make('starting_float')
                                ->formatStateUsing(fn ($state): string => 'Fondo Inicial Caja: Bs. ' . number_format((float) $state, 2)),
                        ]),

                        Stack::make([
                            TextColumn::make('cash_variance')
                                ->formatStateUsing(fn ($state): string => 'Diferencia Efectivo: Bs. ' . number_format((float) $state, 2))
                                ->color(fn ($state): string => abs((float) $state) < 0.01 ? 'success' : (abs((float) $state) <= 5 ? 'warning' : 'danger')),
                            TextColumn::make('qr_variance')
                                ->formatStateUsing(fn ($state): string => 'Diferencia QR: Bs. ' . number_format((float) $state, 2))
                                ->color(fn ($state): string => abs((float) $state) < 0.01 ? 'success' : (abs((float) $state) <= 5 ? 'warning' : 'danger')),
                            TextColumn::make('inventory_variance_value')
                                ->formatStateUsing(fn ($state): string => 'Diferencia Inventario: Bs. ' . number_format((float) $state, 2))
                                ->color(fn ($state): string => abs((float) $state) < 0.01 ? 'success' : 'danger'),
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
                            ->when($data['desde'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('shift_date', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('shift_date', '<=', $date));
                    }),

                SelectFilter::make('shift_template_id')
                    ->label('Tipo de Turno')
                    ->relationship('shiftTemplate', 'name'),

                SelectFilter::make('location')
                    ->label('Ubicación')
                    ->options(fn (): array => OperationalLocation::query()
                        ->orderBy('name')
                        ->pluck('name', 'name')
                        ->all()),

                SelectFilter::make('total_semaphore')
                    ->label('Semáforo Conciliación')
                    ->options(ReconciliationSemaphore::class),

                SelectFilter::make('status')
                    ->label('Estado del Turno')
                    ->options(ShiftStatus::class),
            ])
            ->recordActions([
                Action::make('quick_sale')
                    ->label('Venta POS')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success')
                    ->slideOver()
                    ->modalHeading('Punto de Venta Móvil (POS) — Registro Rápido')
                    ->modalDescription('Seleccione el producto, cantidad y método de pago. El total y la conciliación del turno se actualizan al instante.')
                    ->modalWidth('3xl')
                    ->schema(function (): array {
                        $isSuperAdmin = self::currentAuthUser()?->isSuperAdmin() ?? false;
                        $recalcQuickTotals = function (Get $get, Set $set): void {
                            $qty = max(1, (int) ($get('qty') ?? 1));
                            $price = (float) ($get('unit_price') ?? 0);
                            $set('total_preview', number_format(round($qty * $price, 2), 2, '.', ''));
                        };

                        return [
                            Section::make('1. Fecha y Hora de Registro POS')
                                ->icon('heroicon-m-clock')
                                ->schema([
                                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                                        DatePicker::make('sale_date')
                                            ->label('Fecha de Registro')
                                            ->prefixIcon('heroicon-m-calendar-days')
                                            ->default(fn (): string => now()->toDateString())
                                            ->disabled(! $isSuperAdmin)
                                            ->dehydrated()
                                            ->helperText($isSuperAdmin
                                                ? 'Fecha actual por defecto (editable por Super Administrador).'
                                                : 'Día actual asignado automáticamente (bloqueado para Subadministrador).')
                                            ->required(),

                                        TimePicker::make('sale_time')
                                            ->label('Hora Exacta')
                                            ->prefixIcon('heroicon-m-clock')
                                            ->default(fn (): string => now()->format('H:i:s'))
                                            ->disabled(! $isSuperAdmin)
                                            ->dehydrated()
                                            ->required(),
                                    ]),
                                ]),

                            Section::make('2. Selección de Producto, Cantidad e Importe a Cobrar')
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
                                        ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($recalcQuickTotals): void {
                                            if (! $state) {
                                                return;
                                            }
                                            $product = Product::query()->find($state);
                                            if ($product) {
                                                $set('unit_price', number_format((float) $product->selling_price, 2, '.', ''));
                                                $recalcQuickTotals($get, $set);
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
                                            ->afterStateUpdated(fn (Get $get, Set $set) => $recalcQuickTotals($get, $set)),

                                        Select::make('payment_method')
                                            ->label('Método de Pago')
                                            ->prefixIcon('heroicon-m-qr-code')
                                            ->options(PaymentMethod::posOptions())
                                            ->default(PaymentMethod::QR->value)
                                            ->required(),
                                    ]),

                                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                                        TextInput::make('unit_price')
                                            ->label('Precio Unitario (Bs.)')
                                            ->prefix('Bs.')
                                            ->default('0.00')
                                            ->readOnly(),

                                        TextInput::make('total_preview')
                                            ->label('TOTAL A COBRAR (Bs.)')
                                            ->prefix('Bs.')
                                            ->default('0.00')
                                            ->readOnly()
                                            ->dehydrated(false),
                                    ]),
                                ]),
                        ];
                    })
                    ->action(function (Shift $record, array $data): void {
                        $isSuperAdmin = self::currentAuthUser()?->isSuperAdmin() ?? false;
                        $product = Product::query()->findOrFail($data['product_id']);
                        $nextNum = Sale::query()->count() + 1;

                        Sale::query()->create([
                            'transaction_code' => 'T' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT),
                            'shift_id' => $record->id,
                            'sale_date' => $isSuperAdmin ? ($data['sale_date'] ?? now()->toDateString()) : now()->toDateString(),
                            'sale_time' => $isSuperAdmin ? ($data['sale_time'] ?? now()->format('H:i:s')) : now()->format('H:i:s'),
                            'product_id' => $product->id,
                            'qty' => (int) $data['qty'],
                            'unit_price' => $product->selling_price,
                            'unit_cost' => $product->master_unit_cost,
                            'payment_method' => $data['payment_method'],
                        ]);

                        ShiftReconciliationService::reconcile($record);

                        Notification::make()
                            ->title('Venta registrada y jornada conciliada')
                            ->success()
                            ->send();
                    }),

                Action::make('quick_exception')
                    ->label('Merma')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->slideOver()
                    ->modalHeading('Registro de Merma, Cortesía o Comida de Personal')
                    ->modalWidth('3xl')
                    ->schema([
                        Section::make('Detalle de la Excepción de Inventario')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto (SKU)')
                                    ->options(fn (): array => Product::query()
                                        ->where('is_active', true)
                                        ->orderBy('sku')
                                        ->get()
                                        ->mapWithKeys(fn (Product $p): array => [$p->id => "{$p->sku} — {$p->name}"])
                                        ->all())
                                    ->required()
                                    ->searchable()
                                    ->columnSpanFull(),

                                Grid::make(['default' => 1, 'sm' => 2])->schema([
                                    Select::make('code')
                                        ->label('Código de Excepción')
                                        ->options(ExceptionCode::class)
                                        ->default(ExceptionCode::W02->value)
                                        ->required(),

                                    TextInput::make('qty')
                                        ->label('Cantidad (Unidades)')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->required(),
                                ]),

                                TextInput::make('reason')
                                    ->label('Motivo / Autorización')
                                    ->placeholder('Ej: Error de preparación o comida de personal autorizada')
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (Shift $record, array $data): void {
                        $product = Product::query()->findOrFail($data['product_id']);
                        $nextNum = ShiftException::query()->count() + 1;

                        ShiftException::query()->create([
                            'exception_code' => 'E' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT),
                            'shift_id' => $record->id,
                            'exception_date' => now()->toDateString(),
                            'exception_time' => now()->format('H:i:s'),
                            'product_id' => $product->id,
                            'qty' => (int) $data['qty'],
                            'code' => $data['code'],
                            'reason' => $data['reason'],
                            'unit_price' => $product->selling_price,
                        ]);

                        ShiftReconciliationService::reconcile($record);

                        Notification::make()
                            ->title('Excepción registrada y stock actualizado')
                            ->warning()
                            ->send();
                    }),

                ViewAction::make()
                    ->label('Ver')
                    ->slideOver()
                    ->modalWidth('7xl'),
                EditAction::make()
                    ->label('Conciliar / Editar')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->after(function (Shift $record): void {
                        ShiftReconciliationService::reconcile($record);
                    })
                    ->visible(fn (): bool => self::currentAuthUser()?->isSuperAdmin() ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => self::currentAuthUser()?->isSuperAdmin() ?? false),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SalesRelationManager::class,
            ExceptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
        ];
    }
}
