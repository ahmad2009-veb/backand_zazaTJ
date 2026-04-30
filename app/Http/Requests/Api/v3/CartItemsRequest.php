<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;

class CartItemsRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        return [
            'foods' => 'required|array',
            'foods.*.id' => 'required|int',
            'foods.*.variations' => 'nullable|array',
            'foods.*.variations.*.choice_name' => 'required|string',
            'foods.*.variations.*.value' => 'required|string',
            'foods.*.add_ons' => 'nullable|array',
            'foods.*.add_ons.*' => 'required|int',
        ];
    }
}
