<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function getProductsByRestaurantSlug(Request $request)
    {
        $restaurant = Restaurant::where('slug', $request->slug)
            ->withCount('foods')
            ->get()->map(function ($rest) {
                return [
                    'id' => $rest['id'],
                    'name' => $rest['name'],
                    'slug' => $rest['slug'],
                    'delivery_time' => $rest['delivery_time'],
                    'logo' => $rest['logo'],
                    'foods_count' => $rest['foods_count']
                ];
            })->first();


        $categories = Category::where('parent_id', $restaurant['id'])->get()
            ->map(function ($category) {
                return [

                    'category_id' => $category['id'],
                    'category_name' => $category['name'],
                    'image' => $category['image'],
                    'foods' => $category->foods->map(function ($food) {
                        return [
                            'id' => $food['id'],
                            'name' => $food['name']

                        ];
                    })


                ];
            });
        return response()->json([$restaurant, $categories], 200);
    }
}
