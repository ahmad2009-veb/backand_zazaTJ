<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Add stock to product with weighted average purchase price calculation
     * Supports both simple products and variation-based products
     */
    public function addStock(Product $product, float $quantityToAdd, float $newPurchasePrice, ?string $variationId = null): void
    {
        DB::transaction(function () use ($product, $quantityToAdd, $newPurchasePrice, $variationId) {
            if ($variationId) {
                // Add stock to specific variation
                $this->addVariationStock($product, $variationId, $quantityToAdd, $newPurchasePrice);
            } else {
                // Add stock to base product (legacy)
                $currentQty = (float) $product->quantity;
                $currentPrice = (float) $product->purchase_price;

                $currentValue = $currentQty * $currentPrice;
                $newValue = $quantityToAdd * $newPurchasePrice;
                $totalValue = $currentValue + $newValue;
                $totalQty = $currentQty + $quantityToAdd;

                $newWeightedPrice = $totalQty > 0 ? round($totalValue / $totalQty, 2) : $newPurchasePrice;

                $product->update([
                    'quantity' => $totalQty,
                    'purchase_price' => $newWeightedPrice
                ]);
            }
        });
    }

    /**
     * Add stock to a specific variation
     */
    private function addVariationStock(Product $product, string $variationId, float $quantityToAdd, float $newPurchasePrice): void
    {
        $variation = ProductVariation::where('product_id', $product->id)
            ->where('variation_id', $variationId)
            ->first();

        if ($variation) {
            $currentQty = (float) $variation->quantity;
            $currentPrice = (float) ($variation->cost_price ?? 0);

            $currentValue = $currentQty * $currentPrice;
            $newValue = $quantityToAdd * $newPurchasePrice;
            $totalValue = $currentValue + $newValue;
            $totalQty = $currentQty + $quantityToAdd;

            $newWeightedPrice = $totalQty > 0 ? round($totalValue / $totalQty, 2) : $newPurchasePrice;

            $variation->update([
                'quantity' => $totalQty,
                'cost_price' => $newWeightedPrice
            ]);
        }
    }

    /**
     * Reduce stock (for sales)
     * Supports both simple products and variation-based products
     */
    public function reduceStock(Product $product, float $quantityToReduce, ?string $variationId = null): bool
    {
        if ($variationId) {
            return $this->reduceVariationStock($product, $variationId, $quantityToReduce);
        }

        if ($product->quantity < $quantityToReduce) {
            return false; // Insufficient stock
        }

        $product->update([
            'quantity' => $product->quantity - $quantityToReduce
        ]);

        return true;
    }

    /**
     * Reduce stock from a specific variation
     */
    private function reduceVariationStock(Product $product, string $variationId, float $quantityToReduce): bool
    {
        $variation = ProductVariation::where('product_id', $product->id)
            ->where('variation_id', $variationId)
            ->first();

        if (!$variation || $variation->quantity < $quantityToReduce) {
            return false; // Insufficient stock
        }

        $variation->update([
            'quantity' => $variation->quantity - $quantityToReduce
        ]);

        return true;
    }

    /**
     * Get total quantity across all variations
     */
    public function getTotalQuantity(Product $product): float
    {
        $variationTotal = ProductVariation::where('product_id', $product->id)->sum('quantity') ?? 0;
        return $variationTotal + ($product->quantity ?? 0);
    }
}
