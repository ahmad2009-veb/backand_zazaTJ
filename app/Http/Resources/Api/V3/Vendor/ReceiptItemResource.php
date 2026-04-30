<?php

namespace App\Http\Resources\Api\V3\Vendor;

use App\Enums\VariationTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Get variation type from notes metadata
        $variationType = null;
        if ($this->productVariation && $this->notes) {
            try {
                $metadata = json_decode($this->notes, true);
                if (isset($metadata['variation_type'])) {
                    $variationType = VariationTypeEnum::tryFrom($metadata['variation_type']);
                }
            } catch (\Exception) {
                // If notes is not JSON, ignore
            }
        }

        // Extract variation_type from variation_id
        // Format: {variation_type}_{timestamp}_{index}
        // We extract everything before the first underscore
        $variationType_extracted = null;
        if ($this->productVariation && $this->productVariation->variation_id) {
            $variationId = $this->productVariation->variation_id;
            $firstUnderscorePos = strpos($variationId, '_');
            if ($firstUnderscorePos !== false) {
                $variationType_extracted = substr($variationId, 0, $firstUnderscorePos);
            }
        }

        // Get categories and subcategories from category_ids JSON
        $categories = [];
        $subcategories = [];
        if ($this->product->category_ids) {
            try {
                $categoryIds = json_decode($this->product->category_ids, true);
                if (is_array($categoryIds)) {
                    foreach ($categoryIds as $cat) {
                        if (isset($cat['id']) && isset($cat['position'])) {
                            $categoryId = (int) $cat['id'];
                            $category = \App\Models\Category::find($categoryId);
                            if ($category) {
                                if ($cat['position'] == 1) {
                                    $categories[] = [
                                        'id' => $category->id,
                                        'name' => $category->name,
                                    ];
                                } elseif ($cat['position'] == 2) {
                                    $subcategories[] = [
                                        'id' => $category->id,
                                        'name' => $category->name,
                                    ];
                                }
                            }
                        }
                    }
                }
            } catch (\Exception) {
                // If category_ids is not valid JSON, ignore
            }
        }

        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'code' => $this->product->product_code,
                'categories' => $categories,
                'subcategories' => $subcategories,
            ],
            'variation' => $this->productVariation ? [
                'id' => $this->productVariation->id,
                'variation_id' => $this->productVariation->variation_id,
                'variation_type' => $variationType_extracted,
                'attribute_value' => $this->productVariation->attribute_value,
                'attribute_id' => $this->productVariation->attribute_id,
                'barcode' => $this->productVariation->barcode,
                'cost_price' => (float) $this->productVariation->cost_price,
                'sale_price' => (float) $this->productVariation->sale_price,
                'type' => $variationType?->value,
                'type_name' => $variationType?->typeName(),
                'type_label' => $variationType?->label(),
            ] : null,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}

