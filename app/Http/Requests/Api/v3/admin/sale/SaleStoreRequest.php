<?php

namespace App\Http\Requests\Api\v3\admin\sale;

use App\Enums\SaleStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleStoreRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'status' => ['required', 'string', Rule::enum(SaleStatusEnum::class)],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'delivery_charge' => ['nullable', 'integer'],
            'delivery_man_id' => ['nullable', 'integer', 'exists:delivery_men,id'],
            'products' =>  ['required', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.price' => ['nullable', 'numeric', 'min:0'],
            'products.*.discount' => ['required', 'numeric', 'min:0'],
            'products.*.discount_type' => ['required', 'string', 'in:amount,percent'],
            'products.*.variation' => ['nullable', 'string']
        ];
    }
}
