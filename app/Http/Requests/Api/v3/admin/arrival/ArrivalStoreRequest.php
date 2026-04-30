<?php

namespace App\Http\Requests\Api\v3\admin\arrival;

use Illuminate\Foundation\Http\FormRequest;

class ArrivalStoreRequest extends FormRequest
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
            // 'provider_name' => 'nullable|string',
            // 'provider_phone' => 'nullable|string',
            // 'company_name' => 'nullable|string',
            // 'address' => 'nullable|string',
            // 'identification_info' => 'nullable|string',
            // 'provider_contact' => 'nullable|string',
            // 'warehouse_id' => 'required|int|exists:warehouses,id',
            // 'status' => 'required|string|in:pending,completed,cancelled',
            // 'file' => 'nullable|file|mimes:xlsx,csv',
            // 'products' => 'required|array',
            // 'products.*.product_id' => 'required|int|exists:products,id',
            // 'products.*.quantity' => 'required|int',
            // 'products.*.purchase_price' => 'required|decimal:2',
            // 'products.*.product_code' => 'nullable|string',
            // 'products.*.retail_price' => 'required|int',
            // 'products.*.image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:1024'
            //            'products.*.retail_total_price' => 'required|int',
        ];
    }
}
