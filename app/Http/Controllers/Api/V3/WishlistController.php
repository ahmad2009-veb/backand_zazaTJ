<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantResourceCollection;
use App\Http\Resources\WishlistResource;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller


{
    public function get_customer_wishlist(Request $request)
    {
        $wishlist = Wishlist::query()->where('user_id', auth()->user()->id)->with('restaurant')->get();

        return WishlistResource::collection($wishlist);

    }

    /*============================================================================================================ */

    public function addToWishList(Request $request):JsonResponse
    {

        $data = $request->validate(['restaurant_id' => 'required|integer']);
        $restaurant_id = $data['restaurant_id'];

        $restaurant = Restaurant::where('id', $restaurant_id)->get()->first();

        $wishlist = Wishlist::where('user_id', auth()->user()->id)->where('restaurant_id', $data['restaurant_id'])->get();
        if ($wishlist->isEmpty()) {

            Wishlist::create([
                'user_id' => auth()->user()->id,
                'restaurant_id' => $restaurant_id
            ]);

            return response()->json(['status' => true, 'message' => 'added to wishlist'], 201);

        }

        return response()->json(['status' => false, 'message' => 'item is in wishlist already', 'user_id' => auth()->user()->id], 405);


    }

    /*============================================================================================================ */


    public function remove_from_wishlist(Request $request): JsonResponse
    {
        $data = $request->validate(['restaurant_id' => 'required|integer']);
        $restaurant_id = $data['restaurant_id'];

        $wishlist = Wishlist::where('user_id', auth()->user()->id)->where('restaurant_id', $data['restaurant_id'])->get();


        if (!$wishlist->isEmpty()) {

            Wishlist::where('user_id', auth()->user()->id)->where('restaurant_id', $restaurant_id)->delete();

            return response()->json(['status' => true, 'message' => 'deleted'], 201);

        }

        return response()->json(['status' => false, 'message' => 'not found'], 405);

    }
}

