<?php

namespace App\Http\Resources\Cashbox;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'user_id' => $this->user_id,
            'order_amount' => $this->order_amount,
            'discount' => $this->coupon_discount_amount,
            'discount_type' => $this->discount_type,
            'order_detail' => OrderDetailResource::collection($this->details),
            'bonus_discount_amount' => $this->bonus_discount_amount,
            'fd_data' => json_decode($this->fd_data)
        ];
    }
}
