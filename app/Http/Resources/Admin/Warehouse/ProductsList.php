<?php

namespace App\Http\Resources\Admin\Warehouse;

use App\Http\Resources\CategoryAdminResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductsList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $subCategoryId = collect(json_decode($this['category_ids']))->where('position', 2)->first()?->id;

        // Get variations from product_variations table
        $variations = [];
        $model = $this->resource;

        if ($model && is_object($model)) {
            try {
                $variationModels = null;

                if ($model->relationLoaded('variations')) {
                    $variationModels = $model->getRelation('variations');
                } elseif (method_exists($model, 'variations')) {
                    $variationModels = $model->variations()->get();
                }

                if ($variationModels && is_iterable($variationModels)) {
                    foreach ($variationModels as $variation) {
                        // Extract variation_type from variation_id
                        // Format: {variation_type}_{timestamp}_{index}
                        $variationType = null;
                        if ($variation->variation_id) {
                            $firstUnderscorePos = strpos($variation->variation_id, '_');
                            if ($firstUnderscorePos !== false) {
                                $variationType = substr($variation->variation_id, 0, $firstUnderscorePos);
                            }
                        }

                        $variations[] = [
                            'variation_id' => $variation->variation_id,
                            'variation_type' => $variationType,
                            'attribute_id' => $variation->attribute_id,
                            'attribute_value' => $variation->attribute_value,
                            'cost_price' => $variation->cost_price,
                            'sale_price' => $variation->sale_price,
                            'quantity' => $variation->quantity,
                            'barcode' => $variation->barcode,
                        ];
                    }
                }
            } catch (\Exception) {
                // If relationship fails, fall back to JSON
            }
        }

        // Fallback to old JSON variations if no relationship data was found
        if (empty($variations) && !empty($this['variations']) && is_string($this['variations'])) {
            $variations = json_decode($this['variations']);
        }

        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'price' => $this['price'],
            'image' => url('storage/product/' . $this['image']),
            'status' => $this['status'],
            'category' => CategoryAdminResource::make($this['subCategory'], $subCategoryId),
            'warehouse' => WarehouseResource::make($this['warehouse']),
            'quantity' => $this['quantity'],
            'variations' => $variations
            //            'warehouseProducts' => WarehouseProductResource::collection($this['warehouseProducts']),
        ];
    }
}
