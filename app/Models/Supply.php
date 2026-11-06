<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplyCategory;
use App\Models\Concerns\Auditable;
use App\Services\CostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supply extends Model
{
    use Auditable;
    protected $fillable = [
        'code',
        'name',
        'category',
        'base_unit',
        'content_per_purchase',
        'purchase_unit',
        'last_purchase_price',
        'base_unit_cost',
        'standard_waste_pct',
        'adjusted_unit_cost',
        'main_supplier',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => SupplyCategory::class,
            'content_per_purchase' => 'decimal:4',
            'last_purchase_price' => 'decimal:4',
            'base_unit_cost' => 'decimal:6',
            'standard_waste_pct' => 'decimal:4',
            'adjusted_unit_cost' => 'decimal:6',
            'min_stock' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Supply $supply): void {
            $costs = CostingService::computeSupplyCosts(
                (float) $supply->last_purchase_price,
                (float) $supply->content_per_purchase,
                (float) $supply->standard_waste_pct
            );
            $supply->base_unit_cost = $costs['base_unit_cost'];
            $supply->adjusted_unit_cost = $costs['adjusted_unit_cost'];
        });

        static::saved(function (Supply $supply): void {
            CostingService::propagateSupplyChange($supply);
        });
    }

    public function boms(): HasMany
    {
        return $this->hasMany(ProductBom::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}

