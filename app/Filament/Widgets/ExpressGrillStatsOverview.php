<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ExceptionCode;
use App\Enums\PaymentMethod;
use App\Enums\ReconciliationSemaphore;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftException;
use App\Models\ShiftItem;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ExpressGrillStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;
        $location = $this->pageFilters['location'] ?? null;
        $shiftId = $this->pageFilters['shift_id'] ?? null;

        // 1. Shifts query filtered
        $shiftsQuery = Shift::query()
            ->when($startDate, fn (Builder $q) => $q->whereDate('shift_date', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate('shift_date', '<=', $endDate))
            ->when($location, fn (Builder $q) => $q->where('location', $location))
            ->when($shiftId, fn (Builder $q) => $q->where('id', $shiftId));

        $shiftIds = (clone $shiftsQuery)->pluck('id')->all();

        // 2. Sales aggregate in single optimal query
        $salesStats = Sale::query()
            ->whereIn('shift_id', $shiftIds)
            ->selectRaw('
                COUNT(*) as tx_count,
                COALESCE(SUM(qty), 0) as total_units,
                COALESCE(SUM(sales_value), 0) as total_sales,
                COALESCE(SUM(sales_value - contribution_value), 0) as total_cogs,
                COALESCE(SUM(contribution_value), 0) as total_contribution,
                COALESCE(SUM(CASE WHEN payment_method = ? THEN sales_value ELSE 0 END), 0) as cash_sales,
                COALESCE(SUM(CASE WHEN payment_method = ? THEN sales_value ELSE 0 END), 0) as qr_sales
            ', [PaymentMethod::EFECTIVO->value, PaymentMethod::QR->value])
            ->first();

        $totalSales = (float) ($salesStats->total_sales ?? 0);
        $totalCogs = (float) ($salesStats->total_cogs ?? 0);
        $totalContribution = (float) ($salesStats->total_contribution ?? 0);
        $totalUnits = (int) ($salesStats->total_units ?? 0);
        $txCount = (int) ($salesStats->tx_count ?? 0);
        $cashSales = (float) ($salesStats->cash_sales ?? 0);
        $qrSales = (float) ($salesStats->qr_sales ?? 0);

        $avgTicket = $txCount > 0 ? round($totalSales / $txCount, 2) : 0.0;
        $marginPct = $totalSales > 0 ? round(($totalContribution / $totalSales) * 100, 1) : 0.0;
        $cogsPct = $totalSales > 0 ? round(($totalCogs / $totalSales) * 100, 1) : 0.0;

        // 3. Expenses & Purchases in the selected period
        $totalExpenses = (float) Expense::query()
            ->when($startDate, fn (Builder $q) => $q->whereDate('expense_date', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate('expense_date', '<=', $endDate))
            ->when($shiftId, fn (Builder $q) => $q->where('shift_id', $shiftId))
            ->when($location && ! $shiftId, fn (Builder $q) => $q->where(function (Builder $sub) use ($shiftIds): void {
                $sub->whereIn('shift_id', $shiftIds)->orWhereNull('shift_id');
            }))
            ->sum('amount');

        $totalPurchases = (float) Purchase::query()
            ->when($startDate, fn (Builder $q) => $q->whereDate('purchase_date', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate('purchase_date', '<=', $endDate))
            ->sum('total_cost');

        $operatingProfit = round($totalContribution - $totalExpenses, 2);

        // Break-even point (Punto de Equilibrio)
        $avgUnitContribution = $totalUnits > 0 ? ($totalContribution / $totalUnits) : 0.0;
        $breakEvenUnits = $avgUnitContribution > 0 ? (int) ceil($totalExpenses / $avgUnitContribution) : 0;
        $breakEvenSales = ($marginPct > 0) ? round($totalExpenses / ($marginPct / 100), 2) : 0.0;

        // 4. Reconciliations & Physical Control
        $shiftsAgg = (clone $shiftsQuery)
            ->selectRaw('
                COUNT(*) as shifts_count,
                COALESCE(SUM(cash_variance), 0) as total_cash_variance,
                COALESCE(SUM(qr_variance), 0) as total_qr_variance,
                COALESCE(SUM(inventory_variance_value), 0) as total_inv_variance
            ')
            ->first();

        $totalDispatched = (int) ShiftItem::query()
            ->whereIn('shift_id', $shiftIds)
            ->sum('available_qty');

        $wasteUnits = (int) ShiftException::query()
            ->whereIn('shift_id', $shiftIds)
            ->whereIn('code', [ExceptionCode::W01->value, ExceptionCode::W02->value, ExceptionCode::W03->value])
            ->sum('qty');

        $wasteRetailValue = (float) ShiftException::query()
            ->whereIn('shift_id', $shiftIds)
            ->whereIn('code', [ExceptionCode::W01->value, ExceptionCode::W02->value, ExceptionCode::W03->value])
            ->sum('retail_value');

        $wastePct = $totalDispatched > 0 ? round(($wasteUnits / $totalDispatched) * 100, 2) : 0.0;
        $sellThroughPct = $totalDispatched > 0 ? round(($totalUnits / $totalDispatched) * 100, 1) : 0.0;

        $cashVar = (float) ($shiftsAgg->total_cash_variance ?? 0);
        $qrVar = (float) ($shiftsAgg->total_qr_variance ?? 0);
        $invVar = (float) ($shiftsAgg->total_inv_variance ?? 0);

        $redCount = (clone $shiftsQuery)->where('total_semaphore', ReconciliationSemaphore::ROJO->value)->count();
        $yellowCount = (clone $shiftsQuery)->where('total_semaphore', ReconciliationSemaphore::AMARILLO->value)->count();
        $greenCount = (clone $shiftsQuery)->where('total_semaphore', ReconciliationSemaphore::VERDE->value)->count();

        $semaphoreStatus = $redCount > 0
            ? "ROJO ({$redCount} turno con descuadre)"
            : ($yellowCount > 0 ? "AMARILLO ({$yellowCount} con alerta)" : "VERDE ({$greenCount} turnos exactos)");

        $semaphoreColor = $redCount > 0 ? 'danger' : ($yellowCount > 0 ? 'warning' : 'success');

        return [
            Stat::make('1. Ventas Totales POS', 'Bs. ' . number_format($totalSales, 2))
                ->description(sprintf(
                    'Efectivo: Bs. %s | QR: Bs. %s (%d trans. | Ticket Prom: Bs. %s)',
                    number_format($cashSales, 2),
                    number_format($qrSales, 2),
                    $txCount,
                    number_format($avgTicket, 2)
                ))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('2. Costo de Ventas (Receta BOM)', 'Bs. ' . number_format($totalCogs, 2))
                ->description(sprintf(
                    '%s%% de las ventas | %d unid. vendidas (%s%% sell-through)',
                    number_format($cogsPct, 1),
                    $totalUnits,
                    number_format($sellThroughPct, 1)
                ))
                ->descriptionIcon('heroicon-m-scale')
                ->color('info'),

            Stat::make('3. Contribución Bruta', 'Bs. ' . number_format($totalContribution, 2))
                ->description(sprintf(
                    'Margen Contribución: %s%% (Meta >= 55%%)',
                    number_format($marginPct, 1)
                ))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($marginPct >= 55 ? 'success' : 'warning'),

            Stat::make('4. Resultado Operativo Neto', 'Bs. ' . number_format($operatingProfit, 2))
                ->description(sprintf(
                    'Egresos: Bs. %s | P. Equilibrio: %d unid. (Bs. %s)',
                    number_format($totalExpenses, 2),
                    $breakEvenUnits,
                    number_format($breakEvenSales, 2)
                ))
                ->descriptionIcon('heroicon-m-calculator')
                ->color($operatingProfit >= 0 ? 'success' : 'danger'),

            Stat::make('5. Compras de Insumos', 'Bs. ' . number_format($totalPurchases, 2))
                ->description('Actualiza automáticamente el Costo Maestro BOM')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('6. Conciliación Efectivo y QR', 'Bs. ' . number_format($cashVar + $qrVar, 2))
                ->description(sprintf(
                    'Dif. Caja: Bs. %s | Dif. QR: Bs. %s',
                    number_format($cashVar, 2),
                    number_format($qrVar, 2)
                ))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color((abs($cashVar) < 0.01 && abs($qrVar) < 0.01) ? 'success' : 'danger'),

            Stat::make('7. Mermas y Dif. Inventario', 'Bs. ' . number_format($invVar, 2))
                ->description(sprintf(
                    'Mermas (W01-W03): %d unid. (%s%% vs Meta < 3%%) | Retail: Bs. %s',
                    $wasteUnits,
                    number_format($wastePct, 2),
                    number_format($wasteRetailValue, 2)
                ))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color((abs($invVar) < 0.01 && $wastePct <= 3.0) ? 'success' : 'warning'),

            Stat::make('8. Semáforo de Auditoría', $semaphoreStatus)
                ->description(sprintf(
                    'Verde: %d | Amarillo: %d | Rojo: %d',
                    $greenCount,
                    $yellowCount,
                    $redCount
                ))
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($semaphoreColor),
        ];
    }
}
