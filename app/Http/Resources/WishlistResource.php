<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $restaurant = $this->restaurant;
        return [
            'id' => $restaurant->id,
            'name' => $restaurant->name,
            'slug' => $restaurant->slug,
            'logo' => url('storage/restaurant/'.$restaurant->logo),
            'image' => url('storage/restaurant/cover/'.$restaurant->cover_photo),
            'inWishlist' => $this->additional && in_array($restaurant->id, $this->additional['user_wished_restaurants']),
            'foods_count' => $restaurant->foods->count(),
            'rating' => $restaurant->avg_rating,
            'categories' => CategoryResource::collection($restaurant->categories()),
//            'categories' => $restaurant->categories->map(function ($category) {
//                return [
//                    'id' => $category->id,
//                    'name' => $category->name,
//                    'image' => $category->image
//                ];
//
//            })
        ];
    }
}
