<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantGroupResource extends JsonResource
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
            'logo' => url('storage/restaurant/' . $this->logo),
            'cover_photo' => url('storage/restaurant/cover/' . $this->cover_photo),
            'restaurants' => RestaurantResourceCollection::make($this->restaurants),
        ];
    }
}
