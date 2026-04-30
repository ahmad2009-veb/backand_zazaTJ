<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseTransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        // Helper function to extract variation_type from variation_id
        $extractVariationType = function ($variationId) {
            if (!$variationId) return null;
            $firstUnderscorePos = strpos($variationId, '_');
            if ($firstUnderscorePos !== false) {
                return substr($variationId, 0, $firstUnderscorePos);
            }
            return null;
        };

        // Group items by product and build products array with variations
        $products = [];
        $totalSalePrice = 0;
        $totalCostPrice = 0;
        $totalQuantity = 0;

        if ($this->relationLoaded('items')) {
            $groupedByProduct = [];

            foreach ($this->items as $item) {
                $product = $item->product;
                $variation = $item->productVariation;
                $quantity = (float) $item->quantity;

                // Parse legacy JSON variations to get prices
                $legacyVariations = [];
                if ($product->variations && is_string($product->variations)) {
                    $legacyVariations = json_decode($product->variations, true) ?? [];
                }

                // Get prices from variation or legacy data
                $salePrice = 0;
                $costPrice = 0;
                $variationType = null;
                $variationId = null;
                $attributeValue = null;
                $barcode = null;

                if ($variation) {
                    // Use ProductVariation table data
                    $salePrice = $variation->sale_price ?? 0;
                    $costPrice = $variation->cost_price ?? 0;
                    $variationType = $extractVariationType($variation->variation_id);
                    $variationId = $variation->variation_id;
                    $attributeValue = $variation->attribute_value;
                    $barcode = $variation->barcode;
                } elseif (!empty($legacyVariations)) {
                    // Fallback to first legacy variation prices
                    $firstVariation = $legacyVariations[0] ?? [];
                    $salePrice = (float) ($firstVariation['sale_price'] ?? 0);
                    $costPrice = (float) ($firstVariation['cost_price'] ?? 0);
                    $variationType = $extractVariationType($firstVariation['variation_id'] ?? null);
                    $variationId = $firstVariation['variation_id'] ?? null;
                    $attributeValue = $firstVariation['attribute_value'] ?? null;
                    $barcode = $firstVariation['barcode'] ?? null;
                } else {
                    // Fallback to product fields
                    $salePrice = $product->price ?? 0;
                    $costPrice = $product->purchase_price ?? 0;
                }

                // Add to totals
                $totalQuantity += $quantity;
                $totalSalePrice += $salePrice * $quantity;
                $totalCostPrice += $costPrice * $quantity;

                // Group by product_id
                $productId = $product->id;
                if (!isset($groupedByProduct[$productId])) {
                    $groupedByProduct[$productId] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'image' => $product->image,
                        'product_code' => $product->product_code,
                        'variation_name' => $product->variation_name,
                        'variations' => [],
                    ];
                }

                // Add variation data for this item
                $groupedByProduct[$productId]['variations'][] = [
                    'item_id' => $item->id,
                    'product_variation_id' => $item->product_variation_id,
                    'variation_id' => $variationId,
                    'variation_type' => $variationType,
                    'attribute_value' => $attributeValue,
                    'barcode' => $barcode,
                    'quantity' => $quantity,
                    'cost_price' => $costPrice,
                    'sale_price' => $salePrice,
                    'total_cost_price' => $costPrice * $quantity,
                    'total_sale_price' => $salePrice * $quantity,
                    'notes' => $item->notes,
                ];
            }

            $products = array_values($groupedByProduct);
        }

        $isExternal = $this->transfer_type?->value === 'external';

        $data = [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'name' => $this->name,
            'transfer_type' => $this->transfer_type?->value,
            'status' => $this->status,
            'is_installment' => $this->is_installment ?? false,
            'notes' => $this->notes,
            'transferred_at' => $this->transferred_at?->format('Y-m-d H:i:s'),
            'received_at' => $this->received_at?->format('Y-m-d H:i:s'),
            'from_warehouse' => $this->fromWarehouse ? [
                'id' => $this->fromWarehouse->id,
                'name' => $this->fromWarehouse->name,
            ] : null,
            'to_warehouse' => $this->toWarehouse ? [
                'id' => $this->toWarehouse->id,
                'name' => $this->toWarehouse->name,
            ] : null,
            'products' => $products,
            'total_quantity' => $totalQuantity,
            'total_sale_price' => $totalSalePrice,
            'total_cost_price' => $totalCostPrice,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];

        // Add vendor info for external transfers
        if ($isExternal) {
            $data['from_vendor'] = $this->vendor ? [
                'id' => $this->vendor->id,
                'name' => trim($this->vendor->f_name . ' ' . $this->vendor->l_name),
                'phone' => $this->vendor->phone,
            ] : null;

            $data['to_vendor'] = $this->toVendor ? [
                'id' => $this->toVendor->id,
                'name' => trim($this->toVendor->f_name . ' ' . $this->toVendor->l_name),
                'phone' => $this->toVendor->phone,
            ] : null;

            // Add receipt info for external transfers
            $data['receipt'] = $this->receipt ? [
                'id' => $this->receipt->id,
                'receipt_number' => $this->receipt->receipt_number,
                'status' => $this->receipt->status?->value ?? $this->receipt->status,
                'total_amount' => $this->receipt->total_amount,
            ] : null;

            // Add installment info if exists
            if ($this->relationLoaded('installment') && $this->installment) {
                $data['installment'] = [
                    'id' => $this->installment->id,
                    'initial_payment' => $this->installment->initial_payment,
                    'total_due' => $this->installment->total_due,
                    'remaining_balance' => $this->installment->remaining_balance,
                    'due_date' => $this->installment->due_date?->format('Y-m-d'),
                    'is_paid' => $this->installment->is_paid,
                    'paid_at' => $this->installment->paid_at?->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $data;
    }
}

