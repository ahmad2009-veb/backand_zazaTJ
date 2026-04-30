<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class MainRestaurantResource extends JsonResource
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
            'slug' => $this->slug,
            'logo' => url('storage/restaurant/' . $this->logo),
            'zone' => [
                'id' => $this->zone->id,
                'name' => $this->zone->name,
            ],
            'available_zones' => collect([$this, ...$this->subRestaurants])->map(function ($restaurant) {
                return [
                    'restaurant_id' => $restaurant->id,
                    'zone' => [
                        'id' => $restaurant->zone->id,
                        'name' => $restaurant->zone->name,
                    ],
                ];
            })
        ];
    }
}
