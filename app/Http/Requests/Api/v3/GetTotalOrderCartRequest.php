<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;

class GetTotalOrderCartRequest extends FormRequest
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
            'restaurant_id' => 'required|int|exists:restaurants,id',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:food,id',
            'items.*.qty' => 'required|numeric',
            'items.*.variation' => 'nullable|string',
            'items.*.add_ons' => 'nullable|array',
            'items.*.add_ons.*.id' => 'required_with:items.*.add_ons|numeric',
            'items.*.add_ons.*.qty' => 'required_with:items.*.add_ons|numeric',
        ];
    }
}

