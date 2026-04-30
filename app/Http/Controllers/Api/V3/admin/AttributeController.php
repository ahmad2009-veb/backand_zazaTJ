<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AttributeResource;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Rap2hpoutre\FastExcel\FastExcel;

class AttributeController extends Controller
{
    public function index(Request $request)
    {

        return AttributeResource::collection(Attribute::with('store')->select('id', 'name', 'store_id')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'max:100', Rule::unique('attributes', 'name')->where(function ($query) use ($request) {
                return $query->where('store_id', $request->store_id);
            })],
            'store_id' => 'required|integer|exists:stores,id'
        ]);
        $att = Attribute::query()->create([
            'name' => $request->input('name'),
            'store_id' => $request->store_id
        ]);

        return $att;
    }

    public function edit(Attribute $attribute, Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('attributes', 'name')
                    ->where('store_id', $request->store_id)
                    ->ignore($attribute->id)
            ],
            'store_id' => 'required|integer|exists:stores,id'
        ]);
        $attribute->update([
            'name' => $request->name,
            'store_id' => $request->store_id
        ]);
        return response()->json(['message' => 'ok']);
    }


    public function delete(Attribute $attribute)
    {
        $attribute->delete();
        return response()->json(['message' => 'deleted successfully'], 200);
    }

    public function export_attributes($type)
    {
        $collection = Attribute::all();
        if ($type == 'excel') {
            return (new FastExcel(Helpers::export_categories($collection)))->download('Categories.xlsx');
        } elseif ($type == 'csv') {
            return (new FastExcel(Helpers::export_categories($collection)))->download('Categories.csv');
        }
    }
}
