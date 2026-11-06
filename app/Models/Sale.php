<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use App\Services\ShiftReconciliationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use Auditable;
    protected $fillable = [
        'transaction_code',
        'shift_id',
        'sale_date',
        'sale_time',
        'product_id',
        'qty',
        'unit_price',
        'unit_cost',
        'payment_method',
        'sales_value',
        'contribution_value',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:6',
            'payment_method' => PaymentMethod::class,
            'sales_value' => 'decimal:2',
            'contribution_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Sale $sale): void {
            if ($sale->product_id && ((float) $sale->unit_price <= 0 || (float) $sale->unit_cost <= 0)) {
                $product = Product::query()->find($sale->product_id);
                if ($product) {
                    if ((float) $sale->unit_price <= 0) {
                        $sale->unit_price = $product->selling_price;
                    }
                    if ((float) $sale->unit_cost <= 0) {
                        $sale->unit_cost = $product->master_unit_cost;
                    }
                }
            }

            $authUser = auth()->user();
            if ($authUser instanceof User && ! $authUser->isSuperAdmin() && ! $sale->exists) {
                $sale->sale_date = now()->toDateString();
                $sale->sale_time = now()->format('H:i:s');
            }

            if (empty($sale->sale_date)) {
                $sale->sale_date = now()->toDateString();
            }

            if (empty($sale->sale_time)) {
                $sale->sale_time = now()->format('H:i:s');
            }

            $qty = max(1, (int) $sale->qty);
            $sale->qty = $qty;
            $sale->sales_value = round($qty * (float) $sale->unit_price, 2);
            $sale->contribution_value = round($qty * ((float) $sale->unit_price - (float) $sale->unit_cost), 2);
        });

        static::saved(function (Sale $sale): void {
            if ($sale->shift_id) {
                ShiftReconciliationService::reconcile((int) $sale->shift_id);
            }
        });

        static::deleted(function (Sale $sale): void {
            if ($sale->shift_id) {
                ShiftReconciliationService::reconcile((int) $sale->shift_id);
            }
        });
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

