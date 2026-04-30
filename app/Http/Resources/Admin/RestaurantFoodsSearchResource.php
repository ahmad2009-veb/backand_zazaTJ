<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Cashbox\CashboxProductItemResource;
use App\Http\Resources\ProductResource;
use App\Models\Restaurant;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantFoodsSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $keyword = $request->input('keyword');
        $restaurant_id = $this->additional['restaurant_id'];

        if ($keyword) {
            $restaurant = Restaurant::find($restaurant_id);
            $foods = $restaurant->foods()->where('name', 'like', '%' . $keyword . '%')
                ->get();
        } else {
            $foods = $this->foods()->where('restaurant_id', $restaurant_id)
                ->get();
        }


        return [
            'id' => $keyword ? null : $this['id'],
            'name' => $keyword ? null : $this['name'],
            'priority' => $keyword ? null :$this['priority'],
            'image' => $keyword ? null : ( $this['image'] ? url('storage/category/' . $this['image']) : null),
            'foods' => CashboxProductItemResource::collection($foods)
        ];
    }
}
