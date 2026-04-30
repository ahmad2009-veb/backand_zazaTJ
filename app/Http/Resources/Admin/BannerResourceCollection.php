<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BannerResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($banner) {
            return [
                'id' => $banner['id'],
                'title' => $banner['title'],
                'restaurant' => $banner['restaurant'],
                'status' => $banner['status']
            ];
        });

    }
}
