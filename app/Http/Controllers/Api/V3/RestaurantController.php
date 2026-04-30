<?php

namespace App\Http\Controllers\Api\V3;

use App\CentralLogics\RestaurantLogic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\RestaurantReviewRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\RestaurantCategoryFoodResource;
use App\Http\Resources\RestaurantGroupResource;
use App\Http\Resources\RestaurantResourceCollection;
use App\Http\Resources\RestaurantShowResource;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\RestaurantGroup;
use App\Models\Review;
use App\Models\Wishlist;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class RestaurantController extends Controller
{


    public function index(Request $request)
    {
        $wishedRestaurants = [];
        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();
            $wishedRestaurants = Wishlist::where('user_id', $user['id'])->pluck('restaurant_id')->toArray();
        }

        $restaurants = RestaurantResourceCollection::make(Restaurant::query()->where('restaurant_group_id', null)->get())
            ->additional(['user_wished_restaurants' => $wishedRestaurants]);

//        $restaurants = RestaurantGroup::with('restaurants')->get()
//            ->map(function ($restGroup) use ($wishedRestaurants) {
//
//                if ($restGroup['restaurants']->count() == 1) {
//                    return RestaurantResource::make($restGroup['restaurants'][0]) ;
//                }
//                return [
//                    'id' => $restGroup['id'],
//                    'name' => $restGroup['name'],
//                    'logo' => $restGroup['logo'],
//                    'cover_photo' => $restGroup['cover_photo'],
//                    'restaurants' => RestaurantResourceCollection::make($restGroup['restaurants'])
//                        ->additional(['user_wished_restaurants' => $wishedRestaurants])
//                ];
//            });

        $grouped = RestaurantGroupResource::collection(RestaurantGroup::with('restaurants')->whereHas('restaurants')->get());

        return response()->json([
            'data' => $restaurants,
            'grouped' => $grouped,
        ], 200, [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Charset' => 'utf-8'
        ], JSON_UNESCAPED_UNICODE);
//        if (Auth::guard('api')->check()) {
//            $user = Auth::guard('api')->user();
//            $wishedRestaurants = Wishlist::where('user_id', $user['id'])->pluck('restaurant_id')->toArray();
//            return response()->json(RestaurantResourceCollection::make($restaurants)->additional(['user_wished_restaurants' => $wishedRestaurants]), 200);
//        }
//
//
//        return response()->json(RestaurantResourceCollection::make($restaurants), 200, [
//            'Content-Type' => 'application/json;charset=UTF-8',
//            'Charset' => 'utf-8'
//        ], JSON_UNESCAPED_UNICODE);
    }

    public function getByIds(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|int',
        ]);
        $data = Restaurant::query()->whereIn('id', $request->ids)->select(['id', 'name'])->get();
        return response()->json([
            'data' => $data,
        ]);
    }

    public function show(Restaurant $restaurant)
    {
        return response()->json(
            new RestaurantShowResource($restaurant)
            , 200, [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Charset' => 'utf-8'
        ], JSON_UNESCAPED_UNICODE);

    }


    public function foods(Request $request, Restaurant $restaurant, Category $category): JsonResponse
    {
        return response()->json(
            RestaurantCategoryFoodResource::make($category)->additional([
                'restaurant_id' => $restaurant->id,
            ])
            , 200, [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Charset' => 'utf-8'
        ], JSON_UNESCAPED_UNICODE);
    }


    public function restaurantCategories(Restaurant $restaurant): AnonymousResourceCollection
    {
        return CategoryResource::collection($restaurant->categories());
    }


    public function submitRestaurantReview(RestaurantReviewRequest $request): JsonResponse
    {
        try {
            $restaurant = Restaurant::findOrFail($request['restaurant_id']);

            //Проверка повторного оставления отзыва на один и тот же заказ и ресторран
            $multiple_review = Review::where([
                'restaurant_id' => $request['restaurant_id'],
                'user_id' => $request->user()->id,
                'order_id' => $request['order_id'],
            ])->first();
            // Если есть уже отзыв от пользователя то возвращаем ошибку
            if (isset($multiple_review)) {
                return response()->json(['errors' => ['message' => trans('messages.already_submitted')]], 403);
            }

            // Создание нового row в таблице Reviews
            $review = new Review;
            $review['user_id'] = $request->user()->id;
            $review['order_id'] = $request['order_id'];
            $review['restaurant_id'] = $request['restaurant_id'];
            $review['rating'] = $request['rating'];
            $review->save();

            //Обновление рейтинга ресторана

            //Получение рейтинга  массива  вида [n,n,n,n,n];
            $restaurant_rating = RestaurantLogic::update_restaurant_rating($restaurant->rating, (int)$request['rating']);
            $restaurant->rating = $restaurant_rating;
            $restaurant->save();
            return response()->json(['status' => true, 'message' => 'Отзыв успешно оставлен!'], 201);

        } catch (Exception $ex) {
            return response()->json(["message" => $ex->getMessage()], 500);
        }

    }


}
