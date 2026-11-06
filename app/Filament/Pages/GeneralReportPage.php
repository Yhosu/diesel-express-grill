<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftException;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GeneralReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = '3. Reportes y Administración';

    protected static ?string $navigationLabel = 'Reporte Consolidado Total';

    protected static ?string $title = 'Reporte Consolidado Integral — Diesel Express Grill';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.general-report';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $location = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canAccessReports();
    }

    public function getReportData(): array
    {
        $shiftsQuery = Shift::query()
            ->when($this->startDate, fn (Builder $q) => $q->whereDate('shift_date', '>=', $this->startDate))
            ->when($this->endDate, fn (Builder $q) => $q->whereDate('shift_date', '<=', $this->endDate))
            ->when($this->location, fn (Builder $q) => $q->where('location', $this->location))
            ->orderByDesc('shift_date');

        $shifts = $shiftsQuery->get();
        $shiftIds = $shifts->pluck('id')->all();

        $sales = Sale::query()
            ->with(['product', 'shift'])
            ->whereIn('shift_id', $shiftIds)
            ->orderByDesc('sale_date')
            ->orderByDesc('sale_time')
            ->get();

        $exceptions = ShiftException::query()
            ->with(['product', 'shift'])
            ->whereIn('shift_id', $shiftIds)
            ->orderByDesc('exception_date')
            ->get();

        $purchases = Purchase::query()
            ->with('supply')
            ->when($this->startDate, fn (Builder $q) => $q->whereDate('purchase_date', '>=', $this->startDate))
            ->when($this->endDate, fn (Builder $q) => $q->whereDate('purchase_date', '<=', $this->endDate))
            ->orderByDesc('purchase_date')
            ->get();

        $expenses = Expense::query()
            ->with('shift')
            ->when($this->startDate, fn (Builder $q) => $q->whereDate('expense_date', '>=', $this->startDate))
            ->when($this->endDate, fn (Builder $q) => $q->whereDate('expense_date', '<=', $this->endDate))
            ->when($this->location, fn (Builder $q) => $q->where('location', $this->location))
            ->orderByDesc('expense_date')
            ->get();

        $products = Product::query()
            ->withCount('boms')
            ->orderBy('sku')
            ->get();

        $totalSales = (float) $sales->sum('sales_value');
        $totalContribution = (float) $sales->sum('contribution_value');
        $totalCogs = round($totalSales - $totalContribution, 2);
        $cashSales = (float) $sales->filter(fn (Sale $s): bool => ($s->payment_method instanceof PaymentMethod ? $s->payment_method->value : (string) $s->payment_method) === PaymentMethod::EFECTIVO->value)->sum('sales_value');
        $qrSales = (float) $sales->filter(fn (Sale $s): bool => ($s->payment_method instanceof PaymentMethod ? $s->payment_method->value : (string) $s->payment_method) === PaymentMethod::QR->value)->sum('sales_value');
        $totalExpenses = (float) $expenses->sum('amount');
        $totalPurchases = (float) $purchases->sum('total_cost');
        $totalExceptionsRetail = (float) $exceptions->sum('retail_value');
        $operatingProfit = round($totalContribution - $totalExpenses, 2);

        $locations = Shift::query()
            ->select('location')
            ->distinct()
            ->orderBy('location')
            ->pluck('location')
            ->all();

        return [
            'shifts' => $shifts,
            'sales' => $sales,
            'exceptions' => $exceptions,
            'purchases' => $purchases,
            'expenses' => $expenses,
            'products' => $products,
            'locations' => $locations,
            'kpis' => [
                'total_sales' => $totalSales,
                'cash_sales' => $cashSales,
                'qr_sales' => $qrSales,
                'total_cogs' => $totalCogs,
                'total_contribution' => $totalContribution,
                'total_expenses' => $totalExpenses,
                'total_purchases' => $totalPurchases,
                'total_exceptions_retail' => $totalExceptionsRetail,
                'operating_profit' => $operatingProfit,
                'shifts_count' => $shifts->count(),
                'sales_count' => $sales->count(),
            ],
        ];
    }

    public function resetFilters(): void
    {
        $this->startDate = null;
        $this->endDate = null;
        $this->location = null;
    }

    public function exportExcel(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->getReportData();

        return \App\Services\ReportExcelExportService::export(
            $data,
            $this->startDate,
            $this->endDate,
            $this->location
        );
    }

    protected function getViewData(): array
    {
        return [
            'reportData' => $this->getReportData(),
        ];
    }
}

