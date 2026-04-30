<?php

namespace App\Http\Resources\Cashbox;

use Illuminate\Http\Resources\Json\JsonResource;

class OrdersInvoceResource extends JsonResource
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
            'order_amount' => $this->order_amount,
            'created_at' => $this->created_at,
            'refunded' => $this->refunded,
            'discount' => $this->coupon_discount_amount,
            'bonus_discount_amount' => $this->bonus_discount_amount
        ];
    }
}
