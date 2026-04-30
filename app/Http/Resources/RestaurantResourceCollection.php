<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class RestaurantResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {


        return $this->collection->transform(function ($restaurant) {
            return [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'logo' => url('storage/restaurant/' . $restaurant->logo),
                'image' => url('storage/restaurant/cover/' . $restaurant->cover_photo),
                'rating' => $restaurant->avg_rating,
                'foods_count' => $restaurant->foods->count(),
                'inWishlist' => $this->additional && in_array($restaurant->id, $this->additional['user_wished_restaurants']),
                'categories' => CategoryResource::collection($restaurant->categories()),
            ];
        });


    }
}


