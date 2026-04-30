<?php

namespace App\Http\Requests\Api\v3\admin\warehouse;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string:max:191',
            'image' => 'nullable|image|max:1024',
            'store_id' => 'required|int|exists:stores,id',
            'category_id' => 'required',
            'sub_category_id' => 'nullable',
            'price' => 'required|numeric|between:.01,999999999999.99',
            'discount' => 'required|numeric|min:0',
            'discount_type' => 'required|in:percentage,amount',
            'description' => 'nullable|string|max:1000',
            'veg' => 'nullable|boolean',
            'choice' => 'nullable|array',
            'addons_ids' => 'nullable|array|exists:add_ons,id',
            'product_code' => 'nullable|string:max:191',
            'warehouse_id' => 'nullable|int|exists:warehouses,id',
            'warehouse_qty' => 'required_with:warehouse_id|numeric',
            'warehouse_purchase_price' => 'required_with:warehouse_id|numeric',
            'warehouse_wholesale_price' => 'nullable|numeric',
            'warehouse_retail_price' => 'nullable|numeric',
            'warehouse_status' => 'required_with:warehouse_id|string',

        ];
    }
}
