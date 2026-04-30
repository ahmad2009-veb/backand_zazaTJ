<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class FoodUpdateRequest extends FormRequest
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
            'category_id' => 'required',
            'sub_category_id' => 'nullable',
            'price' => 'required|numeric|between:.01,999999999999.99',
            'purchase_price' => 'required|numeric',
            'quantity' => 'required|integer',
            'discount' => 'required|numeric|min:0',
            'discount_type' => 'required|in:percent,amount',
            'description' => 'nullable|string|max:1000',
            'veg' => 'nullable',
            'choice' => 'nullable|array',
            'product_code' => 'nullable|string',

        ];
    }
}
