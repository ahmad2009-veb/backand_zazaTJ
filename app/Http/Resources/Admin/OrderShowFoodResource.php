<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderShowFoodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $food = json_decode($this->food);
        return [
            'id' => $this->id,
            'price' => $this->price,
            'variation' => json_decode($this->variation),
            'add_ons' => json_decode($this->add_ons),
            'quantity' => $this->quantity,
            'total_add_on_price' => $this->total_add_on_price,
            'details' => [
                'id' => $food->id,
                'name' => $food->name,
                'price' => $food->price,
                'image' => $food->image !== null ? url('storage/product/' . $food->image) : null,
            ],
        ];
    }
}
