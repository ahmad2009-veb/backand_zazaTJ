<?php

namespace App\Http\Resources\Admin\Sale;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'product_name' => $this->product->name,
            'product_image' =>  url('/storage/product/' . $this->product->image),
            'product_purchase_price' => $this->product->purchase_price
        ];
    }
}
