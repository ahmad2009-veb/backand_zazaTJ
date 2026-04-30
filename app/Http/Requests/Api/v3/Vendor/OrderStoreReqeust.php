<?php

namespace App\Http\Requests\Api\v3\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class OrderStoreReqeust extends FormRequest
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
            'customer_id' => 'nullable|exists:users,id',
            'delivery_address' => 'nullable|string',
            'customer_address_id' => 'nullable|exists:customer_addresses,id',
            'delivery_charge' => 'nullable|int',
            // 'store_id' => 'required|exists:stores,id',
            'warehouse_id' => 'nullable|numeric|exists:warehouses,id',
            'comment' => 'nullable|string',
            'comment_for_store' => 'nullable|string',
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.000001',
            'products.*.variation_id' => 'nullable|string',
            'products.*.variation' => 'nullable|string', // Legacy support
            'products.*.price' => 'nullable|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.discount_type' => 'nullable|string|in:currency,percentage',
            'delivery_man_id' => 'nullable|numeric',
            'is_installment' => 'boolean',
            'initial_payment' => 'nullable|numeric',
            'total_due' => 'required_if:is_installment,true|numeric',
            'remaining_balance' => 'required_if:is_installment,true|numeric',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'loyalty_points_used' => 'nullable|numeric|min:0',
        ];
    }
}
