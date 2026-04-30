<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantShowResource;
use App\Models\Food;
use App\Models\OrderDetail;
use App\Models\Restaurant;
use App\Models\RestaurantSearchCount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PayPal\Api\Search;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $keyWord = $request->input('keyword');
        $restaurants = Restaurant::where('name', "Like", '%' . $keyWord . '%')
            ->orWhereHas('foods', function ($query) use ($keyWord) {
                $query->where('name', "Like", '%' . $keyWord . '%');
            })
            ->get();

        $this->increment_count_of_restaurants($restaurants);


        return response()->json([
            'restaurants' => RestaurantShowResource::collection($restaurants)

        ], 200);
    }

    public function user_foods_and_top_searched_rest(Request $request)
    {

        $top_searched_restaurants = Restaurant::join(
            'restaurant_search_counts',
            'restaurants.id',
            '=',
            'restaurant_search_counts.restaurant_id')
            ->orderByDesc('restaurant_search_counts.search_count')
            ->select('restaurants.id', 'restaurants.name', 'restaurants.logo', 'restaurants.delivery_time')
            ->take(3)
            ->get();

        $userFoods = Food::whereIn('id', function ($query) use ($request) {
            $query->select('food_id')
                ->from('order_details')
                ->whereIn('order_id', function ($query) use ($request) {
                    $query->select('id')
                        ->from('orders')
                        ->where('user_id', $request->userId)
                        ->where('order_status', 'delivered');
                });
        })
            ->get();

        return response()->json([
            'userFoods' => $userFoods,
            'top_searched_restaurants' => RestaurantShowResource::collection($top_searched_restaurants),
        ], 200);

    }

    public function increment_count_of_restaurants($restaurants): void
    {
        if ($restaurants) {
            foreach ($restaurants as $restaurant) {
                RestaurantSearchCount::updateOrCreate(
                    ['restaurant_id' => $restaurant->id],
                    ['search_count' => DB::raw('search_count + 1')]
                );
            }
        }
    }

}


