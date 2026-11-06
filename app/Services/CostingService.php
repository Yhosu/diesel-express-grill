<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SupplyCategory;
use App\Models\Product;
use App\Models\ProductBom;
use App\Models\Supply;
use Illuminate\Support\Facades\DB;

class CostingService
{
    /**
     * Calcula el costo por unidad base y el costo ajustado por merma de un insumo.
     */
    public static function computeSupplyCosts(float $lastPurchasePrice, float $contentPerPurchase, float $wastePct): array
    {
        $content = $contentPerPurchase > 0 ? $contentPerPurchase : 1.0;
        $baseUnitCost = $lastPurchasePrice / $content;
        $divisor = (1.0 - $wastePct);
        $adjustedUnitCost = $divisor > 0 ? ($baseUnitCost / $divisor) : $baseUnitCost;

        return [
            'base_unit_cost' => round($baseUnitCost, 6),
            'adjusted_unit_cost' => round($adjustedUnitCost, 6),
        ];
    }

    /**
     * Propaga el cambio de costo de un insumo hacia todas las recetas BOM y productos afectados.
     */
    public static function propagateSupplyChange(Supply $supply): void
    {
        $productIds = ProductBom::query()
            ->where('supply_id', $supply->id)
            ->pluck('product_id')
            ->unique();

        foreach ($productIds as $productId) {
            self::recalculateProduct((int) $productId);
        }
    }

    /**
     * Recalcula el Costo Maestro y desglose BOM de un producto (Hojas 11, 12 y 2 del Excel).
     */
    public static function recalculateProduct(int|Product $product): void
    {
        $productModel = $product instanceof Product
            ? $product
            : Product::query()->find($product);

        if (! $productModel) {
            return;
        }

        DB::transaction(function () use ($productModel): void {
            $boms = ProductBom::query()
                ->with('supply')
                ->where('product_id', $productModel->id)
                ->get();

            $ingredientsCost = 0.0;
            $packagingCost = 0.0;
            $consumablesCost = 0.0;
            $productionVarCost = 0.0;

            foreach ($boms as $bom) {
                $unitCost = (float) ($bom->supply?->adjusted_unit_cost ?? 0);
                $componentCost = round((float) $bom->qty_per_unit * $unitCost, 6);

                $bom->base_unit_cost_snapshot = $unitCost;
                $bom->component_cost = $componentCost;
                $bom->saveQuietly();

                $cat = $bom->supply?->category;
                $catValue = $cat instanceof SupplyCategory ? $cat->value : (string) $cat;

                match ($catValue) {
                    SupplyCategory::INGREDIENTE->value, SupplyCategory::BEBIDA->value => $ingredientsCost += $componentCost,
                    SupplyCategory::EMPAQUE->value => $packagingCost += $componentCost,
                    SupplyCategory::CONSUMIBLE->value => $consumablesCost += $componentCost,
                    SupplyCategory::PRODUCCION->value => $productionVarCost += $componentCost,
                    default => $ingredientsCost += $componentCost,
                };
            }

            $directBomCost = round($ingredientsCost + $packagingCost + $consumablesCost + $productionVarCost, 6);

            // Actualizar % de participación de cada componente en la receta (Columna J de 11_Recetas_BOM)
            if ($directBomCost > 0) {
                foreach ($boms as $bom) {
                    $bom->cost_share_pct = round(((float) $bom->component_cost) / $directBomCost, 6);
                    $bom->saveQuietly();
                }
            }

            $laborCost = (float) $productModel->direct_labor_cost;
            $otherVarCost = (float) $productModel->other_variable_cost;
            $masterUnitCost = round($directBomCost + $laborCost + $otherVarCost, 6);

            $sellingPrice = (float) $productModel->selling_price;
            $contributionAmount = round($sellingPrice - $masterUnitCost, 6);
            $contributionMarginPct = $sellingPrice > 0
                ? round($contributionAmount / $sellingPrice, 6)
                : 0.0;

            $productModel->forceFill([
                'ingredients_cost' => round($ingredientsCost, 6),
                'packaging_cost' => round($packagingCost, 6),
                'consumables_cost' => round($consumablesCost, 6),
                'production_var_cost' => round($productionVarCost, 6),
                'direct_bom_cost' => $directBomCost,
                'master_unit_cost' => $masterUnitCost,
                'contribution_amount' => $contributionAmount,
                'contribution_margin_pct' => $contributionMarginPct,
            ])->saveQuietly();
        });
    }

    /**
     * Recalcula todos los productos activos del catálogo.
     */
    public static function recalculateAllProducts(): void
    {
        $products = Product::query()->get();
        foreach ($products as $product) {
            self::recalculateProduct($product);
        }
    }
}

