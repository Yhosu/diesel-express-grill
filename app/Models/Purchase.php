<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use Auditable;
    protected $fillable = [
        'purchase_code',
        'purchase_date',
        'supplier',
        'invoice_number',
        'supply_id',
        'qty',
        'purchase_unit',
        'unit_price',
        'subtotal',
        'discount',
        'freight',
        'non_recoverable_tax',
        'total_cost',
        'payment_method',
        'responsible',
        'update_supply_cost',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'freight' => 'decimal:2',
            'non_recoverable_tax' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'update_supply_cost' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Purchase $purchase): void {
            $subtotal = round((float) $purchase->qty * (float) $purchase->unit_price, 2);
            $totalCost = round(
                $subtotal - (float) $purchase->discount + (float) $purchase->freight + (float) $purchase->non_recoverable_tax,
                2
            );
            $purchase->subtotal = $subtotal;
            $purchase->total_cost = $totalCost;

            if (empty($purchase->purchase_unit) && $purchase->supply_id) {
                $supply = Supply::query()->find($purchase->supply_id);
                if ($supply) {
                    $purchase->purchase_unit = $supply->purchase_unit;
                }
            }
        });

        static::saved(function (Purchase $purchase): void {
            if ($purchase->update_supply_cost && $purchase->supply_id && (float) $purchase->unit_price > 0) {
                $supply = Supply::query()->find($purchase->supply_id);
                if ($supply && (float) $supply->last_purchase_price !== (float) $purchase->unit_price) {
                    $supply->last_purchase_price = $purchase->unit_price;
                    $supply->save();
                }
            }
        });
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}

