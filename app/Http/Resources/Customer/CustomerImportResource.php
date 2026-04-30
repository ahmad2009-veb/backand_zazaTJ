<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerImportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $import = $this;
        
        // Parse products string to create products array similar to order details
        $productsArray = [];
        if (!empty($import->products)) {
            // Split products by common separators and create product entries
            $productNames = preg_split('/[,;|\n]/', $import->products);
            foreach ($productNames as $index => $productName) {
                $productName = trim($productName);
                if (!empty($productName)) {
                    $productsArray[] = [
                        'id' => $index + 1, // Generate a fake ID for consistency
                        'name' => $productName,
                        'image' => null, // No images for imported products
                        'quantity' => 1, // Default quantity since we don't have individual quantities
                    ];
                }
            }
        }

        return [
            'id' => $import->id,
            'date' => \Carbon\Carbon::parse($import->purchase_date)->format('Y-m-d'),
            'order_amount' => $import->total_order_price,
            'order_quantity' => $import->total_order_count,
            'products' => $productsArray,
            'discount' => $import->discount,
            'points' => $import->user->loyalty_points,
        ];
    }
}
