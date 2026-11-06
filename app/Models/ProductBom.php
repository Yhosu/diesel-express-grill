<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\CostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBom extends Model
{
    use Auditable;
    protected $fillable = [
        'product_id',
        'supply_id',
        'qty_per_unit',
        'base_unit_cost_snapshot',
        'component_cost',
        'cost_share_pct',
        'recipe_version',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_per_unit' => 'decimal:4',
            'base_unit_cost_snapshot' => 'decimal:6',
            'component_cost' => 'decimal:6',
            'cost_share_pct' => 'decimal:6',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (ProductBom $bom): void {
            CostingService::recalculateProduct((int) $bom->product_id);
        });

        static::deleted(function (ProductBom $bom): void {
            CostingService::recalculateProduct((int) $bom->product_id);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}

