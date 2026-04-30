<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AddOnItemResource;
use App\Http\Resources\Admin\AddOnsResource;
use App\Models\AddOn;
use App\Models\Restaurant;
use App\Scopes\RestaurantScope;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class AddOnController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->input('search', '');
        $addons = AddOn::with(['restaurant'])
            ->when('$keyword', function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%");
            })->orderBy('name')->paginate($request->per_page);
        return AddOnsResource::collection($addons);
    }

    public function restaurantAddOns(Request $request, Restaurant $restaurant)
    {
        $addons = AddOn::withoutGlobalScope(RestaurantScope::class)->where('restaurant_id', $restaurant->id)->get();
        return AddOnItemResource::collection($addons);
    }

    public function exportData(Request $request, $type)
    {
//        $request->validate([
//            'type' => 'required',
//            'start_id' => 'required_if:type,id_wise',
//            'end_id' => 'required_if:type,id_wise',
//            'from_date' => 'required_if:type,date_wise',
//            'to_date' => 'required_if:type,date_wise',
//        ]);
//        $addons = AddOn::when($request['type'] == 'date_wise', function ($query) use ($request) {
//            $query->whereBetween('created_at',
//                [$request['from_date'] . ' 00:00:00', $request['to_date'] . ' 23:59:59']);
//        })
//            ->when($request['type'] == 'id_wise', function ($query) use ($request) {
//                $query->whereBetween('id', [$request['start_id'], $request['end_id']]);
//            })
//            ->when($request['type'] == 'all', function ($query) {
//                //
//            })
//            ->withoutGlobalScope(RestaurantScope::class)->get();

        $addons = AddOn::withoutGlobalScope(RestaurantScope::class)->get();


        return (new FastExcel($addons))->download('Addons.' . $request->type);
    }

    public function importData(Request $request)
    {
        try {
            $collections = (new FastExcel)->import($request->file('products_file'));
        } catch (\Exception $exception) {

            return response()->json(['message' => trans('messages.you_have_uploaded_a_wrong_format_file')]);
        }


        $data = [];
        foreach ($collections as $collection) {
            if ($collection['name'] === "" && $collection['restaurant_id'] === "") {


                return response()->json(['message' => trans('messages.please_fill_all_required_fields')]);
            }


            $data[] = [
                'name' => $collection['name'],
                'price' => $collection['price'],
                'restaurant_id' => $collection['restaurant_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('add_ons')->insert($data);
        return response()->json(['message' => trans('messages.addon_imported_successfully')]);

    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'restaurant_id' => 'required|numeric|exists:restaurants,id',
            'price' => 'required|numeric|between:0,999999999999.99'
        ]);

        $addon = new AddOn();
        $addon->name = $request->name;
        $addon->price = $request->price;
        $addon->restaurant_id = $request->restaurant_id;
        $addon->save();

        return response()->json(['message' => trans('messages.addon_added_successfully')], 201);
    }

    public function update(Request $request, AddOn $addOn)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'restaurant_id' => 'required|numeric|exists:restaurants,id',
            'price' => 'required|numeric|between:0,999999999999.99'
        ]);

        $addOn->name = $request->name;
        $addOn->price = $request->price;
        $addOn->restaurant_id = $request->restaurant_id;
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
