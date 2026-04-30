<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryManResource extends JsonResource
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
            'delivery_man_number' => $this->delivery_man_number,
            'f_name' => $this['f_name'],
            'l_name' => $this['l_name'],
            'zone' => $this->zone ? [
                'id' => $this->zone['id'],
                'name' => $this->zone['name']
            ] : null,
            'avatar' => $this['image'] ? url('storage/delivery-man/' . $this['image']) : null,
            'status' => $this['status'],
            'phone' => $this['phone']
        ];
    }
}
