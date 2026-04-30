<?php

namespace App\Http\Requests\Admin\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
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
            'delivery_address' => 'required|string',
            'store_id' => 'nullable|exists:stores,id',
            'comment'=> 'nullable|string',
            'warehouse_id' => 'nullable|numeric',
            'comment_for_store' => 'nullable|string',
            'comment_for_warehouse' => 'nullable|string',
            'delivery_charge' => 'nullable|int',
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.discount' => 'nullable|numeric',
            'products.*.variation' => 'nullable|string',
            'products.*.add_ons' => 'nullable|array',
            'products.*.price' => 'nullable|numeric',
            'is_installment' => 'boolean',
            'initial_payment' => 'nullable|numeric',
            'total_due' => 'required_if:is_installment,true|numeric',
            'remaining_balance' => 'required_if:is_installment,true|numeric',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'products.*.add_ons.*' => 'required_with:items.*.add_ons|numeric',
        ];
    }
}
