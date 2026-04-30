<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantItemResource extends JsonResource
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
            'tax' => $this->tax,
            'delivery_time' => $this->delivery_time,
            'logo' => $this->logo ? url('storage/restaurant/' . $this->logo) : null,
            'cover_photo' => $this->cover_photo ? url('storage/restaurant/cover/' . $this->cover_photo) : null,
            'zone_id' => $this->zone_id,
            'longitude' =>$this->longitude,
            'latitude' => $this->latitude,
            'vendor' => [
                'f_name' => $this->vendor->f_name,
                'l_name' => $this->vendor->l_name,
                'phone' => $this->vendor->phone,
                'email' =>$this->vendor->email,
            ]
        ];
    }
}
