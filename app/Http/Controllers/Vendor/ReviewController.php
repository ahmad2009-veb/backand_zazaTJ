<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Review;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = Review::whereHas('food', function ($query) {
            return $query->where('restaurant_id', Helpers::get_restaurant_id());
        })->latest()->paginate(config('default_pagination'));

        return view('vendor-views.review.index', compact('reviews'));
    }
}
