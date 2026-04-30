<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
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
            'order_amount' => 'nullable',
            'restaurant_id' => 'required',
            'payment_method' => 'required|in:cash_on_delivery,digital_payment,wallet,bonus_discount',
            'delivery_type' => 'required|in:standard,express',
            'delivery_time' => 'required_unless:delivery_type,standard',
            'delivery_address' => 'required',
            'order_note' => 'nullable|max:300',
            'campaign_id' => 'nullable|numeric',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:food,id',
            'items.*.qty' => 'required|numeric',
            'items.*.variation' => 'nullable|string',
            'items.*.add_ons' => 'nullable|array',
            'items.*.add_ons.*' => 'required_with:items.*.add_ons|numeric',
        ];
    }
}

