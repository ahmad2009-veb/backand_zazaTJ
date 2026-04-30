<?php

namespace App\Http\Resources\Admin\Warehouse;

use App\Models\Category;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Handle both WarehouseProduct and Product models
        $isProduct = $this->resource instanceof \App\Models\Product;

        // Parse category_ids from product
        $categoryData = $this->getCategoryData();

        // Get product for variations
        $product = $isProduct ? $this->resource : $this->product;

        // Get variations - use loaded relationship if available
        $variations = collect([]);
        if ($product && method_exists($product, 'variations')) {
            if ($product->relationLoaded('variations')) {
                $variations = collect($product->getRelation('variations'));
            } else {
                $variations = $product->variations()->get();
            }
        }

        // Calculate total quantity:
        // - If product has variations: always sum variation quantities (regardless of WarehouseProduct or Product)
        // - For WarehouseProduct without variations: use warehouse_product.quantity (per-warehouse tracking)
        // - For Product without variations: use Product.quantity
        $totalQuantity = 0;
        if ($variations->count() > 0) {
            // Product has variations - sum variation quantities (this is the correct quantity)
            $totalQuantity = $variations->sum('quantity');
        } elseif (!$isProduct) {
            // This is a WarehouseProduct without variations - use its quantity (per-warehouse)
            $totalQuantity = $this->quantity ?? 0;
        } else {
            // This is a Product without variations - use product quantity
            $totalQuantity = $this->quantity ?? 0;
        }

        return [
            'id' => $isProduct ? $this->id : $this->product_id,
            'warehouse_id' => $isProduct ? $this->warehouse_id : $this->warehouse_id,
            'product_id' => $isProduct ? $this->id : $this->product_id,
            'quantity' => $totalQuantity,
            'purchase_price' => $isProduct ? $this->purchase_price : $this->purchase_price,
            'retail_price' => $isProduct ? $this->price : $this->retail_price,
            'product_code'  => $isProduct ? ($this->product_code ?? null) : $this->product_code,
            'product_name' => $isProduct ? $this->name : $this->product->name,
            'image' => $isProduct ? ($this->image ? url('storage/product/' . $this->image) : null) : (url('storage/' . $this->image) ?? null),
            'category' => $categoryData['category'],
            'subcategory' => $categoryData['subcategory'],
            'variation_name' => $product ? $product->variation_name : null,
            'variations' => $variations->map(function ($variation) {
                // Extract variation_type from variation_id
                // Format: {variation_type}_{timestamp}_{index}
                // We extract everything before the first underscore
                $variationType = null;
                if ($variation->variation_id) {
                    $firstUnderscorePos = strpos($variation->variation_id, '_');
                    if ($firstUnderscorePos !== false) {
                        $variationType = substr($variation->variation_id, 0, $firstUnderscorePos);
                    }
                }

                return [
                    'id' => $variation->id,
                    'variation_id' => $variation->variation_id,
                    'variation_type' => $variationType,
                    'attribute_id' => $variation->attribute_id,
                    'attribute_value' => $variation->attribute_value,
                    'cost_price' => $variation->cost_price,
                    'sale_price' => $variation->sale_price,
                    'quantity' => $variation->quantity,
                    'barcode' => $variation->barcode,
                ];
            })->toArray(),
        ];
    }

    /**
     * Extract category and subcategory from product's category_ids JSON
     */
    private function getCategoryData()
    {
        $category = null;
        $subcategory = null;

        // Get the product - either directly (if Product model) or via relationship (if WarehouseProduct model)
        $product = $this->resource instanceof \App\Models\Product ? $this->resource : $this->product;

        if ($product && $product->category_ids) {
            $categoryIds = json_decode($product->category_ids, true);

            if (is_array($categoryIds)) {
                foreach ($categoryIds as $cat) {
                    if (isset($cat['position']) && isset($cat['id'])) {
                        $categoryModel = Category::find($cat['id']);

                        if ($categoryModel) {
                            if ($cat['position'] == 1) {
                                $category = [
                                    'id' => $categoryModel->id,
                                    'name' => $categoryModel->name,
                                ];
                            } elseif ($cat['position'] == 2) {
                                $subcategory = [
                                    'id' => $categoryModel->id,
                                    'name' => $categoryModel->name,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return [
            'category' => $category,
            'subcategory' => $subcategory,
        ];
    }
}


