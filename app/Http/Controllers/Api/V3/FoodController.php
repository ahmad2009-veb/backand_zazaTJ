<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductItemResource;
use App\Http\Resources\ProductResource;
use App\Models\Food;
use App\Models\Restaurant;
use App\Services\RestaurantService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodController extends Controller
{
    public function getFoodById(Food $food)
    {
        return ProductItemResource::make($food);
    }

    public function getFoodsByIds(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|int',
        ]);

        return ProductItemResource::collection(Food::query()->whereIn('id', $request->ids)->get());
    }

    public function getBestSellers(Restaurant $restaurant, Request $request): JsonResource
    {

        $data = $restaurant->foods()->active()->whereNotIn('id', $request['ids'])->orderByDesc('order_count')->take(12)->get();

        return ProductResource::collection($data);
    }
}
