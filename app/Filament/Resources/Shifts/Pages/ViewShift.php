<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts\Pages;

use App\Enums\ExceptionCode;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\Product;
use App\Models\Sale;
use App\Models\ShiftException;
use App\Models\User;
use App\Services\ShiftReconciliationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;

class ViewShift extends ViewRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $isSuperAdmin = $user instanceof User && $user->isSuperAdmin();

        $recalcQuickTotals = function (Get $get, Set $set): void {
            $qty = max(1, (int) ($get('qty') ?? 1));
            $price = (float) ($get('unit_price') ?? 0);
            $set('total_preview', number_format(round($qty * $price, 2), 2, '.', ''));
        };

        return [
            Action::make('quick_sale')
                ->label('Registrar Venta POS')
                ->icon('heroicon-o-shopping-cart')
                ->color('success')
                ->slideOver()
                ->modalHeading('Punto de Venta Móvil (POS) — Registro Rápido')
                ->modalDescription('Seleccione el producto, cantidad y método de pago. La fecha y hora actuales se registran automáticamente.')
                ->modalWidth('4xl')
                ->schema([
                    Section::make('1. Fecha y Hora de Registro POS')
                        ->icon('heroicon-m-clock')
                        ->schema([
                            Grid::make(2)->schema([
                                DatePicker::make('sale_date')
                                    ->label('Fecha de Registro')
                                    ->prefixIcon('heroicon-m-calendar-days')
                                    ->default(fn (): string => now()->toDateString())
                                    ->disabled(! $isSuperAdmin)
                                    ->dehydrated()
                                    ->helperText($isSuperAdmin
                                        ? 'Fecha actual por defecto (editable por Super Administrador).'
                                        : 'Día actual asignado automáticamente (no editable por Subadministrador).')
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
                            Grid::make(2)->schema([
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
                                    }),

                                Select::make('payment_method')
                                    ->label('Método de Pago')
                                    ->prefixIcon('heroicon-m-qr-code')
                                    ->options(PaymentMethod::posOptions())
                                    ->default(PaymentMethod::QR->value)
                                    ->required(),
                            ]),

                            Grid::make(3)->schema([
                                TextInput::make('qty')
                                    ->label('Cantidad Vendida (Uds.)')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => $recalcQuickTotals($get, $set)),

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
                ])
                ->action(function (array $data) use ($isSuperAdmin): void {
                    $product = Product::query()->findOrFail($data['product_id']);
                    $nextNum = Sale::query()->count() + 1;

                    Sale::query()->create([
                        'transaction_code' => 'T' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT),
                        'shift_id' => $this->record->id,
                        'sale_date' => $isSuperAdmin ? ($data['sale_date'] ?? now()->toDateString()) : now()->toDateString(),
                        'sale_time' => $isSuperAdmin ? ($data['sale_time'] ?? now()->format('H:i:s')) : now()->format('H:i:s'),
                        'product_id' => $product->id,
                        'qty' => (int) $data['qty'],
                        'unit_price' => $product->selling_price,
                        'unit_cost' => $product->master_unit_cost,
                        'payment_method' => $data['payment_method'],
                    ]);

                    ShiftReconciliationService::reconcile($this->record);
                    $this->refreshFormData([
                        'expected_cash_sales',
                        'expected_qr_sales',
                        'actual_cash_sales',
                        'cash_variance',
                        'qr_variance',
                        'inventory_variance_value',
                        'gross_contribution',
                    ]);

                    Notification::make()
                        ->title('Venta registrada y jornada conciliada')
                        ->success()
                        ->send();
                }),

            Action::make('quick_exception')
                ->label('Registrar Merma / Cortesía')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->slideOver()
                ->modalHeading('Registro de Merma, Cortesía o Comida de Personal')
                ->modalWidth('3xl')
                ->schema([
                    Section::make('Detalle de la Excepción de Inventario')
                        ->schema([
                            Grid::make(3)->schema([
                                Select::make('product_id')
                                    ->label('Producto (SKU)')
                                    ->options(fn (): array => Product::query()
                                        ->where('is_active', true)
                                        ->orderBy('sku')
                                        ->get()
                                        ->mapWithKeys(fn (Product $p): array => [$p->id => "{$p->sku} — {$p->name}"])
                                        ->all())
                                    ->required()
                                    ->searchable(),

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
                                ->required(),
                        ]),
                ])
                ->action(function (array $data): void {
                    $product = Product::query()->findOrFail($data['product_id']);
                    $nextNum = ShiftException::query()->count() + 1;

                    ShiftException::query()->create([
                        'exception_code' => 'E' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT),
                        'shift_id' => $this->record->id,
                        'exception_date' => now()->toDateString(),
                        'exception_time' => now()->format('H:i:s'),
                        'product_id' => $product->id,
                        'qty' => (int) $data['qty'],
                        'code' => $data['code'],
                        'reason' => $data['reason'],
                        'unit_price' => $product->selling_price,
                    ]);

                    ShiftReconciliationService::reconcile($this->record);
                    $this->refreshFormData([
                        'inventory_variance_value',
                        'gross_contribution',
                    ]);

                    Notification::make()
                        ->title('Excepción registrada y stock actualizado')
                        ->warning()
                        ->send();
                }),

            EditAction::make()
                ->label('Editar / Conciliar Turno')
                ->visible($isSuperAdmin),
        ];
    }
}
