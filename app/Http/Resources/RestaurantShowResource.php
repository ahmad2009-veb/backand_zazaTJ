<?php

namespace App\Http\Resources;

use App\Models\Food;
use App\Models\Wishlist;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user = auth('api')->user();
        if (!empty($user)) {
            $inWishList = (bool) Wishlist::query()->where(['user_id' => $user->id, 'restaurant_id' => $this->id])->count();
        }
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'logo' => url('storage/restaurant/' . $this['logo']),
            'foods_count' => $this['foods']->count(),
            'in_wish_list' => $inWishList ?? false,
            'cover_photo' => url('storage/restaurant/cover' . $this['cover_photo']),
            'rating' => $this['avg_rating'],
            'delivery_time' => $this['delivery_time'],
            'categories' => $this->categories()->map(fn($category) => $category->name)->slice(0, 4),
        ];
    }
}



