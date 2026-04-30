<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
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
            'id' => $this['id'],
            'title' => $this['title'],
            'image' => url('storage/campaign/' . $this['image']),
            'end_date' => $this['end_date'],
            'restaurants' => RestaurantResourceCollection::make($this['restaurants']),
            'items' => collect($this['items'])->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'food_id' => $item['food_id'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'variant' => $item['variant'],
                    'add_ons' => $item->get_addOns($item['add_ons']),
                    'food' => [
                        'id' => $item['food']->id,
                        'name' => $item['food']->name,
                        'image' => url('storage/product/' . $item['food']->image),
                    ]
                ];
            }),
        ];
    }
}








