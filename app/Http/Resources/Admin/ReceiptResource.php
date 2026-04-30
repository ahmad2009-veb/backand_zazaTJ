<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
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
            'source' => $this->store ? $this->store->name : ($this->warehouse ? $this->warehouse->name : 'Центральный склад'),
            'source_logo' => $this->store ? ($this->store->logo ? url('storage/store' . $this->store->logo) : null) : null,
            'order_id' => $this->id,
            'source_phone' => $this->store ? $this->store->phone : ($this->warehouse ? $this->warehouse->phone : null),
            'created_at' => $this->created_at,
            'total_product_price' => $this->details()->sum('price'),
            'delivery_charge' => $this->delivery_charge,
            'details' => ReceiptShowProductsResource::collection($this->details),
            'total_price' => $this->details()->sum('price') + $this->delivery_charge
        ];
    }
}

