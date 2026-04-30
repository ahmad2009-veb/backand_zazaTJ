<?php

namespace App\Http\Resources\Admin;

use App\Models\Category;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Parse category_ids to get category data
        $categoryData = $this->getCategoryData();

        // Get product variations (only for Product model)
        $productVariations = [];
        if (method_exists($this->resource, 'variations')) {
            try {
                // Use the already-loaded relationship if available, otherwise query it
                $variations = null;
                if ($this->resource->relationLoaded('variations')) {
                    $variations = $this->resource->getRelation('variations');
                } else {
                    $variations = $this->resource->variations()->get();
                }

                if ($variations && (is_array($variations) ? count($variations) : $variations->count()) > 0) {
                    $productVariations = collect($variations)->map(function ($variation) {
                        return [
                            'id' => $variation->id,
                            'variation_id' => $variation->variation_id,
                            'attribute_id' => $variation->attribute_id,
                            'attribute_value' => $variation->attribute_value,
                            'cost_price' => $variation->cost_price,
                            'sale_price' => $variation->sale_price,
                            'quantity' => $variation->quantity,
                            'barcode' => $variation->barcode,
                        ];
                    })->toArray();
                }
            } catch (\Exception) {
                // If there's an error loading variations, just return empty array
                $productVariations = [];
            }
        }

        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'price' => $this['price'],
            'description' => $this['description'],
            'store_id' => $this['store_id'],
            'category_ids' => json_decode($this['category_ids']),
            'category' => $categoryData['category'],
            'subcategory' => $categoryData['subcategory'],
            'veg' => $this['veg'],
            'discount' => $this['discount'],
            'discount_type' => $this['discount_type'],
            'purchase_price' => $this->purchase_price,
            'quantity' => $this->quantity,
            'image' => $this['image'] ? url('storage/product/' . $this['image']) : null,
            'choice_options' => json_decode($this['choice_options']),
            'product_code' => $this['product_code'],
            'available_time_starts' => $this['available_time_starts'],
            'available_time_ends' => $this['available_time_ends'],
            'variation_name' => $this->variation_name,
            'variations' => $productVariations,
        ];
    }

    /**
     * Get category data from category_ids JSON
     */
    private function getCategoryData()
    {
        $category = null;
        $subcategory = null;

        // Check if category_ids exists and is not null
        if (!isset($this['category_ids']) || empty($this['category_ids'])) {
            return [
                'category' => null,
                'subcategory' => null,
            ];
        }

        $categoryIds = json_decode($this['category_ids'], true);
        if (is_array($categoryIds)) {
            foreach ($categoryIds as $cat) {
                // Ensure position and id keys exist
                if (!isset($cat['position']) || !isset($cat['id'])) {
                    continue;
                }

                if ($cat['position'] == 1) {
                    $categoryModel = Category::find($cat['id']);
                    if ($categoryModel) {
                        $category = [
                            'id' => $categoryModel->id,
                            'name' => $categoryModel->name,
                        ];
                    }
                } elseif ($cat['position'] == 2) {
                    $categoryModel = Category::find($cat['id']);
                    if ($categoryModel) {
                        $subcategory = [
                            'id' => $categoryModel->id,
                            'name' => $categoryModel->name,
                        ];
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

