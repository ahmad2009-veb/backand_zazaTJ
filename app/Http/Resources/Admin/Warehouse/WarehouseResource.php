<?php

namespace App\Http\Resources\Admin\Warehouse;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
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
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'status' => $this->status,
            'responsible' => $this->responsible,
            'products' => WarehouseProductResource::collection($this->whenLoaded('warehouseProducts')),
            'owner' => $this->whenLoaded('owner'),
            'store_id' => $this->store_id
        ];
    }
}
