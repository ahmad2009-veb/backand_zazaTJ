<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantAdminResource extends JsonResource
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
            'logo' => url('storage/restaurant/' . $this->logo),
            'phone' => $this->phone,
            'banner' => url('storage/restaurant/cover/' . $this->cover_photo),
//            'zone' => [
//                'id' => $this->zone->id,
//                'name' => $this->zone->name,
//            ],
            'address' => $this->address,
            'status' => $this->status,
            'vendor' => $this->vendor ? [
                'id' => $this->vendor->user_number ?? $this->vendor->id,
                'f_name' => $this->vendor->f_name,
                'l_name' => $this->vendor->l_name,
                'email' => $this->vendor->email,
                'phone' => $this->vendor->phone,
            ] : null,
        ];
    }
}
