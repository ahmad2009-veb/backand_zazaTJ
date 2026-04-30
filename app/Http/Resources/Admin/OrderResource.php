<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
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
            'store' => $this->store ? [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'address' => $this->store->address,
//                'zone' => [
//                    'id' => $this->store->zone?->id,
//                    'name' => $this->store->zone?->name,
//                ],
            ] : null,
            'warehouse' => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name
            ] : ($this->store || $this->warehouse_id ? null : [
                'id' => 9999,
                'name' => 'Центральный склад',
            ]),
            'total' => $this->order_amount + $this->total_add_ons_price,
            'source' => $this->source,
            'status' => $this->order_status,
            'created_at' => $this->created_at,
        ];
    }
}
