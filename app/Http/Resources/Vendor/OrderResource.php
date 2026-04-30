<?php

namespace App\Http\Resources\Vendor;

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
       $deliveryAddress = json_decode($this->delivery_address, true) ?? [];
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer' => [
                'id' => $this->customer ? $this->customer->id : 0,
                'user_number' => $this->customer ? $this->customer->user_number : null,
                'f_name' => $this->customer ? $this->customer->f_name : $deliveryAddress['contact_person_name'] ?? '',
                'l_name' => $this->customer ? $this->customer->l_name : null,
                'phone' => $this->customer ? $this->customer->phone : $deliveryAddress['contact_person_number'] ?? '',
            ],
            'total' => $this->order_amount + $this->total_add_ons_price,
            'installment' => $this->orderInstallment?->remaining_balance ?? 0,
            'source' => $this->source,
            'status' => $this->order_status,
            'created_at' => $this->created_at,
            'products' => $this->products,
            'wallets' => $this->wallets ?? [],
            'comment' => $this->comment
        ];
    }
}
