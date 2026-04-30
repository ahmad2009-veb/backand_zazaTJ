<?php

namespace App\Http\Resources\Admin\Arrivals;

use Illuminate\Http\Resources\Json\JsonResource;

class ArrivalsListResource extends JsonResource
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
            'warehouse' => $this->warehouse->name,
            'company_name' => $this->company_name,
            'total_price' => $this->warehouseProducts->sum(function ($product) {
                return $product->purchase_price * $product->quantity;
            })
        ];
    }
}
