<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AttributeResource;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Traits\VendorEmployeeAccess;

class AttributeController extends Controller
{
    use VendorEmployeeAccess;
    public function index(Request $request)
    {
        $vendor = $this->getActingVendor();
        $search = $request->input('search');
        $attributes = $vendor->attributes()
            ->when($search, function ($query) use ($search) {
                return $query->where('attributes.name', 'like', '%' . $search . '%');
            })
            ->paginate($request->input('per_page', 12));
        return AttributeResource::collection($attributes);
    }

    public function store(Request $request)
    {
        $storeId =  $this->getActingVendor()?->store?->id;
        $request->validate([
            'name' => ['required', 'string', Rule::unique('attributes', 'name')->where('store_id', $storeId)],
        ]);
        $att = Attribute::query()->create([
            'name' => $request->input('name'),
            'store_id' => $storeId
        ]);

        return $att;
    }

    public function update(Request $request, Attribute $attribute)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('attributes')->ignore($attribute->id)->where('store_id', $attribute->store_id)
            ],
        ]);

        $attribute->name = $request->input('name');
        $attribute->save();
        return $attribute;
    }

    public function destroy(Attribute $attribute)
    {
        $attribute->delete();
        return response()->json(['message' => 'Удалено успешно']);
    }
}
