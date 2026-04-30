<?php

namespace App\Http\Controllers\Api\cashbox;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RestaurantFoodsSearchResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SearchFoodResource;
use App\Http\Resources\SearchFoodResourceCollection;
use App\Http\Resources\UserResource;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashierController extends Controller
{
    public function getCashierRestaurant(Request $request)
    {
        $cashier = auth()->user();
        return $cashier['restaurant'];
    }

    public function getCashierOrders(Request $request)
    {
        $cashier = auth()->user();
        $restaurant = $cashier['restaurant'];
        $orders = $restaurant->orders()->orderBy('schedule_at', 'desc')->paginate(config('default_pagination'));
        return $orders;
    }

    public function getFoods()
    {
        $cashier = auth()->user();
        $restaurantFoods = $cashier->restaurant->foods()->paginate(config('default_pagination'));
        return $restaurantFoods;
    }

    public function getResCategories()
    {
        $cashier = auth()->user();
        $role = $cashier->role;
        if (strtolower($role->name) !== 'кассир') {
            return response()->json(['message' => 'access denied'], 401);
        }
        $restaurant = $cashier->restaurant;

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }
        $restaurantCategories = $restaurant->categories();
        return CategoryResource::collection($restaurantCategories);
    }


    public function getFoodsByCatId(Category $category, Request $request)
    {
        $cashier = auth()->user();
        $restaurant = $cashier->restaurant;
        return response()->json(
            RestaurantFoodsSearchResource::make($category)->additional([
                'restaurant_id' => $restaurant->id,
            ])
            , 200, [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Charset' => 'utf-8'
        ], JSON_UNESCAPED_UNICODE);
    }


    public function getUsers(Restaurant $restaurant)
    {

        return Admin::where('restaurant_id', $restaurant->id)->whereHas('role', function ($q) {
            $q->where('name', 'Cashier');
        })->get()->map(function ($el) {
            return [
                'id' => $el['id'],
                'name' => $el['f_name'],
                'email' => $el['email'],
                'image' => $el['image'] ? url('storage/profile.' . $el['image']) : null
            ];
        });


    }

    public function getUserPoint(Request $request)
    {
        $request->validate([
            'phone' => 'required|min:13'
        ]);

        $user = User::query()->where(['phone' => $request->phone, 'status' => 1])->first();

        return UserResource::make($user);
    }

}
