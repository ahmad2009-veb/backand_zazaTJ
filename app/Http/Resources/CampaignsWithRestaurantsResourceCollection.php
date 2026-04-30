<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CampaignsWithRestaurantsResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */


    public function toArray($request)
    {

        return $this->collection->map(function ($campaign) {
            return [
                'id' => $campaign['id'],
                'title' => $campaign['title'],
                'image' => url('storage/campaign/' . $campaign['image']),
                'restaurants' => RestaurantResourceCollection::make($campaign['restaurants'])
            ];


        });


    }
}


