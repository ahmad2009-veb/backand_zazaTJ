<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptShowProductsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $product = json_decode($this->product);
        return [
            'id' => $this->id,
            'price' => $this->price,
            'variation' => json_decode($this->variation),
            'quantity' => $this->quantity,
//            'total_add_on_price' => $this->total_add_on_price,
            'name' => $this->product->name,
//            'details' => [
//                'id' => $product->id,
//                'name' => $product->name,
//                'price' => $product->price,
//                'image' => $product->image !== null ? url('storage/product/' . $product->image) : null,
//            ],

        ];
    }
}
