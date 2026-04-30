<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResoursce extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_status' => $this['order_status'],
            'total' => $this['order_amount'],
            'restaurant_id' => $this['restaurant_id'],
            'created_at' => $this['created_at'],
            'details' => $this['details']->map(function($detail) {
                return [
                    'id' => $detail->food->id,
                    'name' => $detail->food->name,
                    'price' => $detail->food->price,
                    'quantity' => $detail->quantity,
                    'variant' =>$detail->variation,
                    'image' => url('storage/product/' . $detail->food->image),
                    'add_ons' =>json_decode($detail->add_ons)
                ];
            }),
        ];
    }
}


