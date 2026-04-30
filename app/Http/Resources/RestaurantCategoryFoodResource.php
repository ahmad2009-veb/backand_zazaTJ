<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantCategoryFoodResource extends JsonResource
{
//    /**
//     * Transform the resource into an array.
//     *
//     * @param \Illuminate\Http\Request $request
//     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
//     */
    public function toArray($request)
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'priority' => $this['priority'],
            'image' => $this['image'] ? url('storage/category/' . $this['image']): null,
            'foods' => ProductResource::collection($this->foods()->where('restaurant_id', $this->additional['restaurant_id'])->get())
        ];
    }
}
