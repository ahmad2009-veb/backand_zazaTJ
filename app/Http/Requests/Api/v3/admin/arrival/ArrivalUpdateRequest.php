<?php

namespace App\Http\Requests\Api\v3\admin\arrival;

use Illuminate\Foundation\Http\FormRequest;

class ArrivalUpdateRequest extends FormRequest
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
            'name' => 'required|string',
            'provider_name' => 'nullable|string',
            'provider_phone' => 'nullable|string',
            'company_name' => 'nullable|string',
            'address' => 'nullable|string',
            'identification_info' => 'nullable|string',
            'provider_contact' => 'nullable|string',
            'warehouse_id' => 'required|int|exists:warehouses,id',
            'status' => 'required|string|in:pending,completed,cancelled',
            'products' => 'required|array',
            'products.*.product_id' => 'required|int|exists:products,id',
            'products.*.id' => 'nullable|int|exists:warehouse_product,id',
            'products.*.quantity' => 'required|int',
            'products.*.purchase_price' => 'required|int',
            'products.*.product_code' => 'nullable|string',
            'products.*.retail_price' => 'required|int',
            //            'products.*.retail_total_price' => 'required|int',
        ];
    }
}
