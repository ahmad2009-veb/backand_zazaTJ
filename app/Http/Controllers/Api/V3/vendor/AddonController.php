<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AddOnsResource;
use App\Models\AddOn;
use Illuminate\Http\Request;

class AddonController extends Controller
{
    public function index(Request $request)
    {
        $vendor = auth()->user();
        $restaurant = $vendor->restaurants->first();
        $keyword = $request->input('search', '');
        $addons = AddOn::with(['restaurant'])->where('restaurant_id', $restaurant->id)
            ->when('$keyword', function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%");
            })->orderBy('name')->paginate($request->per_page);
        return AddOnsResource::collection($addons);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'price' => 'required|numeric|between:0,999999999999.99'
        ]);
        $vendor = auth()->user();
        $restaurant = $vendor->restaurants->first();
        $addon = new AddOn();
        $addon->name = $request->name;
        $addon->price = $request->price;
        $addon->restaurant_id = $restaurant->id;
        $addon->save();

        return response()->json(['message' => trans('messages.addon_added_successfully')], 201);
    }

    public function update(Request $request, AddOn $addOn)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'price' => 'required|numeric|between:0,999999999999.99'
        ]);


        $addOn->name = $request->name;
        $addOn->price = $request->price;

        $addOn->save();

        return response()->json(['message' => trans('messages.addon_updated_successfully')], 201);
    }

    public function status(Request $request, AddOn $addOn)
    {

        $addOn->status = $request->status;
        $addOn->save();
        return response()->json(['message' => trans('messages.addon_updated_successfully')], 200);
    }

    public function delete(Request $request, AddOn $addOn)
    {

        $addOn->delete();
        return response()->json(['message' => trans('messages.addon_deleted_successfully')]);
    }
}
