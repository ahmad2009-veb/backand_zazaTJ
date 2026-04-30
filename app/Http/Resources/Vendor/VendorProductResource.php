<?php

namespace App\Http\Resources\Vendor;

use App\Http\Resources\CategoryAdminResource;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorProductResource extends JsonResource
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
            'id' => $this['id'],
            'name' => $this['name'],
            'price' => $this['price'],
            'image' => url('storage/product/' . $this['image']),
            'status' => $this['status'],
            'category' => CategoryAdminResource::make($this['subCategory'])

        ];
    }
}

