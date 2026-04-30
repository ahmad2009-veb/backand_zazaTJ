<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantShowResource extends JsonResource
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
            'name' => $this->name,
            'phone' => $this->phone,
            'logo' => url('storage/restaurant/' . $this->logo),
            'banner' => url('storage/restaurant/cover/' . $this->cover_photo),
            'address' => $this->address,
            'map_link' => $this->map_link,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'delivery_time' => $this->delivery_time,
            'tax' => $this->tax,
            'vendor' => [
                'id' => $this->vendor->user_number ?? $this->vendor->id,
                'f_name' => $this->vendor->f_name,
                'l_name' => $this->vendor->l_name,
                'email' => $this->vendor->email,
                'phone' => $this->vendor->phone,
            ],
//            'zone' => [
//                'id' => $this->zone->id,
//                'name' => $this->zone->name,
//            ],
//            'main_restaurant' => $this->mainRestaurant ? [
//                'id' => $this->mainRestaurant->id,
//                'name' => $this->mainRestaurant->name,
//            ] : null,
            'status' => $this->status,
        ];
    }
}
