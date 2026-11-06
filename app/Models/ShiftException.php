<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExceptionCode;
use App\Models\Concerns\Auditable;
use App\Services\ShiftReconciliationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftException extends Model
{
    use Auditable;
    protected $table = 'exceptions';

    protected $fillable = [
        'exception_code',
        'shift_id',
        'exception_date',
        'exception_time',
        'product_id',
        'qty',
        'code',
        'reason',
        'unit_price',
        'retail_value',
    ];

    protected function casts(): array
    {
        return [
            'exception_date' => 'date',
            'code' => ExceptionCode::class,
            'unit_price' => 'decimal:2',
            'retail_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ShiftException $exception): void {
            if ($exception->product_id && (float) $exception->unit_price <= 0) {
                $product = Product::query()->find($exception->product_id);
                if ($product) {
                    $exception->unit_price = $product->selling_price;
                }
            }

            if (empty($exception->exception_date) && $exception->shift_id) {
                $shift = Shift::query()->find($exception->shift_id);
                if ($shift) {
                    $exception->exception_date = $shift->shift_date;
                }
            }

            $qty = max(1, (int) $exception->qty);
            $exception->qty = $qty;
            $exception->retail_value = round($qty * (float) $exception->unit_price, 2);
        });

        static::saved(function (ShiftException $exception): void {
            if ($exception->shift_id) {
                ShiftReconciliationService::reconcile((int) $exception->shift_id);
            }
        });

        static::deleted(function (ShiftException $exception): void {
            if ($exception->shift_id) {
                ShiftReconciliationService::reconcile((int) $exception->shift_id);
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

