<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FoodResource;
use App\Models\Food;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;

class NewOrderController extends Controller
{

    public function getRestaurantByZone(Zone $zone)
    {
        return $zone->restaurants->map(function ($res) {
            return [
                'id' => $res['id'],
                'name' => $res['name']
            ];
        });
    }

    public function getCategoriesByRestaurant(Restaurant $restaurant)
    {
        return $restaurant->categories()->map(function ($cat) {
            return [
                'id' => $cat['id'],
                'name' => $cat['name']
            ];
        });
    }

    public function searchFoods(Request $request)
    {
        $request->validate([
            'zone_id' => 'nullable|numeric',
            'restaurant_id' => 'required|numeric',
            'category_id' => 'required|numeric'
        ]);

        $foods = Food::query()->when($request->zone_id, function ($query) use ($request) {
            return $query->whereHas('restaurant', function ($q) use ($request) {
                return $q->where('zone_id', $request->zone_id);
            });
        })->when($request->restaurant_id, function ($query) use ($request) {
            $query->where('restaurant_id', $request->restaurant_id);
        })->when($request->category_id, function ($query) use ($request) {
            return $query->whereHas('category', function ($q) use ($request) {
                return $q->where('id', $request->category_id);
            });
        })
            ->paginate($request->per_page);

        return FoodResource::collection($foods);

    }

    public function getUsers(Request $request)
    {
        return User::query()->limit(5)->get()->map(function ($el) {
            return [
                'id' => $el['id'],
                'name' => $el['name'],
                'phone' => $el['phone']
            ];
        });
    }

    public function searchUser(Request $request)
    {
        $request->validate([
            'key' => 'required|string'
        ]);

        $userQuery = User::query()->where('f_name', 'like', '%' . $request->key . '%')
            ->orWhere('phone', 'like', '%' . $request->key . '%');


        $users = $userQuery->paginate($request->per_page);
        $users->getCollection()->transform(function ($el) {
            return [
                'id' => $el['id'],
                'name' => $el['f_name'],
                'phone' => $el['phone']
            ];
        });
        return $users;
    }

    public function addUser(Request $request)
    {
        $request->validate([
                'f_name' => 'required|string',
                'l_name' => 'required|string',
                'phone' => 'required|min:13|max:13',
            ]
        );


        $user = User::query()->where('phone', $request->input('phone'))->get();
        if ($user->isEmpty()) {
            $newUser = User::query()->create([
                'f_name' => $request->input('f_name'),
                'l_name' => $request->input('l_name'),
                'phone' => $request->input('phone'),
                'password' => bcrypt(Str::random()),
            ]);


            return response()->json([
                'message' => 'Новый пользователь сохранен',
                'user' => [
                    'id' => $newUser->id,
                    'name_with_phone' => $newUser->name_with_phone
                ]
            ], 201);
        }

        return response()->json(['message' => 'Пользователь существует'], 409);
    }

    public function addAddress(User $user, Request $request)
    {
        $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'address_type' => ['required', 'string'],
            'road' => ['required', 'string', 'max:255'],
            'house' => ['nullable', 'string', 'max:255'],
        ],
            ['road.required' => 'Укажите улицу']
        );
        $point = new Point($request->latitude, $request->longitude);
        $zones = Zone::query()->whereContains('coordinates', $point)->get('id');


        return $user->addresses()->create([
            'contact_person_number' => $user->phone,
            'address_type' => $request->address_type,
            'road' => $request->road,
            'house' => $request->house,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'zone_id' => $zones->first()->id,
        ]);
    }

}
