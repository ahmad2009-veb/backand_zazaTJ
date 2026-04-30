<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashboxOrderRequest extends FormRequest
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
            'user_id' => 'nullable|numeric',
            'restaurant_id' => 'required',
            'discount_for_order' => 'nullable|boolean',
            'discount_type' => 'required_if:discount_for_order,1|in:percent,fixed',
            'discount' => 'nullable|numeric',
            'take_bonus' =>'nullable|numeric',
            'payment_method' => 'required|in:cash,digital_payment,bonus_discount',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:food,id',
            'items.*.qty' => 'required|numeric',
            'items.*.variation' => 'nullable|string',
            'items.*.discount' => 'nullable|numeric',
            'items.*.discount_type' => 'required_with:items.*.discount|in:percent,fixed',
            'items.*.add_ons' => 'nullable|array',
            'items.*.add_ons.*.id' => 'required_with:items.*.add_ons|numeric',
            'items.*.add_ons.*.qty' => 'required_with:items.*.add_ons|numeric',
            // fd_data
            'fd_data' => 'required|array'
        ];

    }
}




