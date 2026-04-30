<?php

namespace App\Http\Requests\Admin\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'customer_id' => 'required|exists:users,id',
            'delivery_address' => 'nullable|string',
            'customer_address_id' => 'nullable|exists:customer_addresses,id',
            'delivery_charge' => 'nullable|int',
            'store_id' => 'nullable|exists:stores,id',
            'warehouse_id' => 'nullable|numeric',
            'comment' => 'nullable|string',
            'comment_for_store' => 'nullable|string',
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.variation' => 'nullable|string',
            'products.*.price' => 'nullable|numeric',
            'delivery_man_id' => 'nullable|numeric',
        ];
    }
}

