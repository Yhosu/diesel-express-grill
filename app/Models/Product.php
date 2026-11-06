<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\CostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable;
    protected $fillable = [
        'sku',
        'name',
        'selling_price',
        'ingredients_cost',
        'packaging_cost',
        'consumables_cost',
        'production_var_cost',
        'direct_bom_cost',
        'direct_labor_cost',
        'other_variable_cost',
        'master_unit_cost',
        'contribution_amount',
        'contribution_margin_pct',
        'portion_control',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'ingredients_cost' => 'decimal:6',
            'packaging_cost' => 'decimal:6',
            'consumables_cost' => 'decimal:6',
            'production_var_cost' => 'decimal:6',
            'direct_bom_cost' => 'decimal:6',
            'direct_labor_cost' => 'decimal:4',
            'other_variable_cost' => 'decimal:4',
            'master_unit_cost' => 'decimal:6',
            'contribution_amount' => 'decimal:6',
            'contribution_margin_pct' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Product $product): void {
            CostingService::recalculateProduct($product);
        });
    }

    public function boms(): HasMany
    {
        return $this->hasMany(ProductBom::class);
    }

    public function shiftItems(): HasMany
    {
        return $this->hasMany(ShiftItem::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShiftException::class);
    }
}

