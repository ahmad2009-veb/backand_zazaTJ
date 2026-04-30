<?php

namespace App\Http\Resources\Admin\Arrivals;

use App\Http\Resources\Admin\Warehouse\WarehouseProductResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ArrivalResource extends JsonResource
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
            'status' => $this->status,
            'created_at' => $this->created_at,
            'company_name' => $this->company_name,
            'provider_phone' => $this->provider_phone,
            'address' => $this->address,
            'provider_name' => $this->provider_name,
            'identification_info' => $this->identification_info,
            'provider_contact' => $this->provider_contact,
            'warehouse_id' => $this->warehouse_id,
            'products' => WarehouseProductResource::collection($this->whenLoaded('warehouseProducts')),
        ];
    }
}
