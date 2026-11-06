<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ShiftReconciliationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftItem extends Model
{
    protected $fillable = [
        'shift_id',
        'product_id',
        'unit_price',
        'unit_cost',
        'opening_qty',
        'replenish_qty',
        'available_qty',
        'sales_potential',
        'sold_qty',
        'waste_qty',
        'comp_qty',
        'staff_qty',
        'closing_qty',
        'variance_qty',
        'variance_value',
        'batch_notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:6',
            'sales_potential' => 'decimal:2',
            'variance_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (ShiftItem $item): void {
            if ($item->shift_id) {
                ShiftReconciliationService::reconcile((int) $item->shift_id);
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

