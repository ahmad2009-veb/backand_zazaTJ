<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
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
            'title' => $this->title,
            'image' => url('storage/banner/' . $this->image),
//            'zone' => [
//                'id' => $this->zone->id,
//                'name' => $this->zone->name,
//            ],
            'store' => $this->store ? [
                'id' => $this->store->id,
                'name' => $this->store->name,
//                'zone_id' => $this->restaurant->zone_id,
            ] : null,
            'status' => $this->status,
        ];
    }
}
