<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
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
            'store_number' => $this->store_number,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo ? url('storage/restaurant/' . $this->logo) : null,
            // 'zone' => [
            //     'id' => $this->zone->id,
            //     'name' => $this->zone->name,
            // ],
        ];
    }
}
