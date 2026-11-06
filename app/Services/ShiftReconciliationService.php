<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExceptionCode;
use App\Enums\PaymentMethod;
use App\Enums\ReconciliationSemaphore;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftException;
use App\Models\ShiftItem;
use Illuminate\Support\Facades\DB;

class ShiftReconciliationService
{
    /**
     * Inicializa las 10 filas de productos (DN01..DN10) al crear un turno si aún no existen.
     */
    public static function ensureShiftItemsInitialized(Shift $shift): void
    {
        ShiftItem::withoutEvents(function () use ($shift): void {
            $products = Product::query()
                ->where('is_active', true)
                ->orderBy('sku')
                ->get();

            foreach ($products as $product) {
                ShiftItem::query()->firstOrCreate(
                    [
                        'shift_id' => $shift->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'unit_price' => $product->selling_price,
                        'unit_cost' => $product->master_unit_cost,
                        'opening_qty' => 0,
                        'replenish_qty' => 0,
                        'available_qty' => 0,
                        'sales_potential' => 0,
                        'sold_qty' => 0,
                        'waste_qty' => 0,
                        'comp_qty' => 0,
                        'staff_qty' => 0,
                        'closing_qty' => 0,
                        'variance_qty' => 0,
                        'variance_value' => 0,
                    ]
                );
            }
        });
    }

    /**
     * Ejecuta la doble conciliación (ALIMENTOS ↔ DINERO) exacta de las Hojas 3, 4, 5, 6 y 7 del Excel.
     */
    public static function reconcile(int|Shift $shift): void
    {
        $shiftModel = $shift instanceof Shift
            ? $shift
            : Shift::query()->find($shift);

        if (! $shiftModel) {
            return;
        }

        DB::transaction(function () use ($shiftModel): void {
            self::ensureShiftItemsInitialized($shiftModel);

            // 1. Agrupar ventas por producto y por método de pago en una sola consulta optimizada
            $salesByProduct = Sale::query()
                ->where('shift_id', $shiftModel->id)
                ->selectRaw('product_id, SUM(qty) as total_qty, SUM(sales_value) as total_sales')
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            $salesByPayment = Sale::query()
                ->where('shift_id', $shiftModel->id)
                ->selectRaw('payment_method, SUM(sales_value) as total_sales')
                ->groupBy('payment_method')
                ->pluck('total_sales', 'payment_method');

            // 2. Agrupar excepciones por producto y código
            $exceptions = ShiftException::query()
                ->where('shift_id', $shiftModel->id)
                ->selectRaw('product_id, code, SUM(qty) as total_qty')
                ->groupBy('product_id', 'code')
                ->get();

            $wasteByProduct = [];
            $compByProduct = [];
            $staffByProduct = [];

            foreach ($exceptions as $exc) {
                $pid = (int) $exc->product_id;
                $codeStr = $exc->code instanceof ExceptionCode ? $exc->code->value : (string) $exc->code;
                $qty = (int) $exc->total_qty;

                if (in_array($codeStr, ['W01', 'W02', 'W03'], true)) {
                    $wasteByProduct[$pid] = ($wasteByProduct[$pid] ?? 0) + $qty;
                } elseif ($codeStr === 'C01') {
                    $compByProduct[$pid] = ($compByProduct[$pid] ?? 0) + $qty;
                } elseif ($codeStr === 'S01') {
                    $staffByProduct[$pid] = ($staffByProduct[$pid] ?? 0) + $qty;
                }
            }

            // 3. Conciliar cada uno de los 10 productos del turno
            $items = ShiftItem::query()
                ->with('product')
                ->where('shift_id', $shiftModel->id)
                ->get();

            $totalSalesPotential = 0.0;
            $totalDispatchedQty = 0;
            $totalSoldQty = 0;
            $totalWasteQty = 0;
            $totalCompQty = 0;
            $totalStaffQty = 0;
            $totalClosingQty = 0;
            $totalInvVarianceQty = 0;
            $totalInvVarianceValue = 0.0;
            $totalCogs = 0.0;
            $grossContribution = 0.0;

            foreach ($items as $item) {
                $pid = (int) $item->product_id;
                $unitPrice = (float) ($item->unit_price > 0 ? $item->unit_price : ($item->product?->selling_price ?? 0));
                $unitCost = (float) ($item->unit_cost > 0 ? $item->unit_cost : ($item->product?->master_unit_cost ?? 0));

                $opening = (int) $item->opening_qty;
                $replenish = (int) $item->replenish_qty;
                $available = $opening + $replenish;
                $potential = round($available * $unitPrice, 2);

                $sold = (int) ($salesByProduct[$pid]->total_qty ?? 0);
                $waste = (int) ($wasteByProduct[$pid] ?? 0);
                $comp = (int) ($compByProduct[$pid] ?? 0);
                $staff = (int) ($staffByProduct[$pid] ?? 0);
                $closing = (int) $item->closing_qty;

                // Fórmula exacta Hoja 6: Inicial + Reposición - Vendido - Merma - Cortesía - Personal - Conteo Final
                // (O visto como Conteo Final - Esperado: si sobra o falta)
                // En el Excel Hoja 6 celda J7: =C7+D7-E7-F7-G7-H7-I7 y L7 (Valor diferencia): =J7*Precio (o con signo negativo cuando falta)
                // En el Excel cuando Inicial=40, Vendido=2, Merma=1, Conteo Final=40 -> J7 = 40+0-2-1-0-0-40 = -3
                $varianceQty = $available - $sold - $waste - $comp - $staff - $closing;
                $varianceValue = round($varianceQty * $unitPrice, 2);

                $item->forceFill([
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'available_qty' => $available,
                    'sales_potential' => $potential,
                    'sold_qty' => $sold,
                    'waste_qty' => $waste,
                    'comp_qty' => $comp,
                    'staff_qty' => $staff,
                    'variance_qty' => $varianceQty,
                    'variance_value' => $varianceValue,
                ])->saveQuietly();

                $totalSalesPotential += $potential;
                $totalDispatchedQty += $available;
                $totalSoldQty += $sold;
                $totalWasteQty += $waste;
                $totalCompQty += $comp;
                $totalStaffQty += $staff;
                $totalClosingQty += $closing;
                $totalInvVarianceQty += $varianceQty;
                $totalInvVarianceValue += $varianceValue;

                $itemCogs = round($sold * $unitCost, 2);
                $itemContrib = round($sold * ($unitPrice - $unitCost), 2);
                $totalCogs += $itemCogs;
                $grossContribution += $itemContrib;
            }

            // 4. Conciliación Financiera (Efectivo y QR por separado)
            $expectedCashSales = round((float) ($salesByPayment[PaymentMethod::EFECTIVO->value] ?? 0), 2);
            $expectedQrSales = round((float) ($salesByPayment[PaymentMethod::QR->value] ?? 0), 2);
            $totalExpectedSales = round($expectedCashSales + $expectedQrSales, 2);

            $startingFloat = (float) $shiftModel->starting_float;
            $countedCash = (float) $shiftModel->counted_cash;
            $confirmedQr = (float) $shiftModel->confirmed_qr;

            // En el Excel Hoja 6 C20: =E3-K3 (Efectivo Contado - Fondo Inicial)
            $actualCashSales = round($countedCash - $startingFloat, 2);
            $totalActualSales = round($actualCashSales + $confirmedQr, 2);

            $cashVariance = round($actualCashSales - $expectedCashSales, 2);
            $qrVariance = round($confirmedQr - $expectedQrSales, 2);
            $totalFinancialVariance = round($totalActualSales - $totalExpectedSales, 2);

            $cashSemaphore = ReconciliationSemaphore::evaluate($cashVariance);
            $qrSemaphore = ReconciliationSemaphore::evaluate($qrVariance);

            // Semáforo global del turno: considera diferencia financiera y diferencia de inventario
            $worstDiff = max(abs($cashVariance), abs($qrVariance), abs($totalInvVarianceValue));
            $totalSemaphore = ReconciliationSemaphore::evaluate($worstDiff);

            $wastePct = $totalDispatchedQty > 0
                ? round($totalWasteQty / $totalDispatchedQty, 6)
                : 0.0;

            $shiftModel->forceFill([
                'sales_potential' => round($totalSalesPotential, 2),
                'expected_cash_sales' => $expectedCashSales,
                'expected_qr_sales' => $expectedQrSales,
                'actual_cash_sales' => $actualCashSales,
                'total_expected_sales' => $totalExpectedSales,
                'total_actual_sales' => $totalActualSales,
                'cash_variance' => $cashVariance,
                'qr_variance' => $qrVariance,
                'total_financial_variance' => $totalFinancialVariance,
                'cash_semaphore' => $cashSemaphore->value,
                'qr_semaphore' => $qrSemaphore->value,
                'total_semaphore' => $totalSemaphore->value,
                'total_dispatched_qty' => $totalDispatchedQty,
                'total_sold_qty' => $totalSoldQty,
                'total_waste_qty' => $totalWasteQty,
                'total_comp_qty' => $totalCompQty,
                'total_staff_qty' => $totalStaffQty,
                'total_closing_qty' => $totalClosingQty,
                'total_inventory_variance_qty' => $totalInvVarianceQty,
                'inventory_variance_value' => round($totalInvVarianceValue, 2),
                'waste_pct' => $wastePct,
                'total_cogs' => round($totalCogs, 2),
                'gross_contribution' => round($grossContribution, 2),
            ])->saveQuietly();
        });
    }
}
