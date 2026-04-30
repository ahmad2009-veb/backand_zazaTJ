<?php

namespace App\Http\Controllers\Web;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Restaurant;

class RestaurantController extends Controller
{
    /**
     * @param \App\Models\Restaurant $restaurant
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function show(Restaurant $restaurant)
    {
        $restaurant->load('foods.subCategory', 'discount');

        return view('web.restaurants.show', [
            'restaurant' => Helpers::restaurant_data_formatting($restaurant),
        ]);
    }
}
