<?php

namespace App\Http\Controllers\Api\V3;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryResourceCollection;
use App\Http\Resources\RestaurantResourceCollection;
use App\Http\Resources\RestaurantsWithCategory;
use App\Models\Category;
use App\Models\Food;
use App\Models\Restaurant;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $categories = Category::where(['position' => 0, 'status' => 1])->orderBy('priority', 'desc')->get();
            return response()->json(CategoryResource::collection($categories), 200);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 200);
        }
    }

    public function getRestaurantsByCategoryId(Category $category)
    {



        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();

            $wishedRestaurants = Wishlist::where('user_id', $user['id'])->pluck('restaurant_id')->toArray();
            return response()->json(RestaurantsWithCategory::make($category)
                ->additional(['user_wished_restaurants' => $wishedRestaurants]), 200);

        }
        return response()->json(RestaurantsWithCategory::make($category), 200);
    }


}
