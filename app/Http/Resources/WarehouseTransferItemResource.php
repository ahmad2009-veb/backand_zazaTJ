<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseTransferItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $product = $this->product;
        $variation = $this->productVariation;
        $quantity = (float) $this->quantity;

        // Parse legacy JSON variations if available
        $legacyVariations = [];
        $legacySalePrice = 0;
        $legacyCostPrice = 0;
        $legacyQuantity = 0;

        if ($product->variations && is_string($product->variations)) {
            $legacyVariations = json_decode($product->variations, true) ?? [];
            if (!empty($legacyVariations) && is_array($legacyVariations)) {
                // Get first variation's prices as default, or sum all quantities
                $firstVariation = $legacyVariations[0] ?? [];
                $legacySalePrice = (float) ($firstVariation['sale_price'] ?? 0);
                $legacyCostPrice = (float) ($firstVariation['cost_price'] ?? 0);
                // Sum all variations quantities
                foreach ($legacyVariations as $v) {
                    $legacyQuantity += (float) ($v['quantity'] ?? 0);
                }
            }
        }

        // Get prices from: 1) ProductVariation table, 2) product direct fields, 3) legacy JSON variations
        $salePrice = $variation?->sale_price
            ?? ($product->price > 0 ? $product->price : null)
            ?? $legacySalePrice
            ?? 0;
        $costPrice = $variation?->cost_price
            ?? ($product->purchase_price > 0 ? $product->purchase_price : null)
            ?? $legacyCostPrice
            ?? 0;
        $variationQuantity = $variation?->quantity
            ?? ($product->quantity > 0 ? $product->quantity : null)
            ?? $legacyQuantity
            ?? 0;

        // Helper function to extract variation_type from variation_id
        // Format: {variation_type}_{timestamp}_{index} e.g. "2kg_1764313034233_0" => "2kg"
        $extractVariationType = function ($variationId) {
            if (!$variationId) return null;
            $firstUnderscorePos = strpos($variationId, '_');
            if ($firstUnderscorePos !== false) {
                return substr($variationId, 0, $firstUnderscorePos);
            }
            return null;
        };

        $variationName = $product->variation_name; // e.g. "WeightClass"

        // Only show the SELECTED variation for this transfer item, not all product variations
        $variationData = null;
        if ($variation) {
            $variationData = [
                'id' => $variation->id,
                'variation_id' => $variation->variation_id,
                'variation_type' => $extractVariationType($variation->variation_id),
                'name' => $variation->name,
                'attribute_id' => $variation->attribute_id,
                'attribute_value' => $variation->attribute_value,
                'cost_price' => $variation->cost_price,
                'sale_price' => $variation->sale_price,
                'quantity' => $variation->quantity,
                'barcode' => $variation->barcode,
            ];
        }
        // Note: If no variation is selected (product_variation_id is null),
        // product_variation will be null - we don't show all legacy variations

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $product->image,
                'product_code' => $product->product_code,
                'variation_name' => $variationName, // e.g. "WeightClass"
                'price' => $product->price > 0 ? $product->price : $legacySalePrice,
                'purchase_price' => $product->purchase_price > 0 ? $product->purchase_price : $legacyCostPrice,
                'quantity' => $product->quantity > 0 ? $product->quantity : $legacyQuantity,
                'warehouse_id' => $product->warehouse_id,
            ],
            'product_variation_id' => $this->product_variation_id,
            'product_variation' => $variationData,
            'quantity' => $quantity,
            'sale_price' => $salePrice,
            'cost_price' => $costPrice,
            'available_quantity' => $variationQuantity,
            'total_sale_price' => $salePrice * $quantity,
            'total_cost_price' => $costPrice * $quantity,
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}

