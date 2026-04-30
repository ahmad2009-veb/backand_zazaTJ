<?php

namespace App\Http\Resources\Cashbox;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $food = json_decode($this['food_details']);
        return [
            'id' => $this['id'],
            'food_id' => $this['food_id'],
            'name' => $food->name,
            'food_price' => !empty($food->variations) ? array_filter($food->variations, function ($var){
                return  $var->type == json_decode($this['variant']);
            })[0]->price : $food->price ,
            'price' => $this['price'],
            'variant' => json_decode($this['variant']),
            'discount_on_food' => $this['discount_on_food'],
            'discount_type' => $this['discount_type'],
            'quantity' => $this['quantity'],
            'addons' => json_decode($this['add_ons'])
        ];
    }
}
