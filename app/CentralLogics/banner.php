<?php

namespace App\CentralLogics;

use App\Models\Banner;
use App\Models\Food;
use App\Models\Restaurant;

class BannerLogic
{
    public static function get_banners($zone_id = null)
    {
        $banners = Banner::active()
            ->when($zone_id, function ($query) use ($zone_id) {
                $query->whereIn('zone_id', $zone_id);
            })
            ->get();

        $data = [];
        foreach ($banners as $banner) {
            if ($banner->type == 'restaurant_wise') {
                $restaurant = Restaurant::find($banner->data);
                $data[]     = [
                    'id'         => $banner->id,
                    'title'      => $banner->title,
                    'type'       => $banner->type,
                    'image'      => $banner->image,
                    'restaurant' => $restaurant ? Helpers::restaurant_data_formatting($restaurant, false) : null,
                    'food'       => null,
                ];
            }
            if ($banner->type == 'item_wise') {
                $food   = Food::find($banner->data);
                $data[] = [
                    'id'         => $banner->id,
                    'title'      => $banner->title,
                    'type'       => $banner->type,
                    'image'      => $banner->image,
                    'restaurant' => null,
                    'food'       => $food ? Helpers::product_data_formatting($food, false, false,
                        app()->getLocale()) : null,
                ];
            }
        }

        return $data;
    }
}
