<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantsWithCategory extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $response = [
            'id' => $this['id'],
            'name' => $this['name'],
            'restaurants' => RestaurantResourceCollection::make($this->restaurants()->each(function ($restaurant) {
                return $restaurant;
            }))];

        if (isset($this->additional['user_wished_restaurants'])) {
            $response['restaurants']->additional(['user_wished_restaurants' => $this->additional['user_wished_restaurants']]);
        }

        return $response;
    }
}
