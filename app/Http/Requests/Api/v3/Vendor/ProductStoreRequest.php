<?php

namespace App\Http\Requests\Api\v3\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class ProductStoreRequest extends FormRequest
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
            'name' => 'required|string|max:191',
            'image' => 'nullable|image|max:1024',
            'category_id' => 'required|integer|exists:categories,id',
            'sub_category_id' => 'nullable|integer|exists:categories,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',

            // Base pricing (can be overridden by variations)
            'price' => 'required|numeric|between:.01,999999999999.99',
            'purchase_price' => 'required|numeric|min:0',

            // Total quantity (sum of all variations)
            'quantity' => 'required|numeric|between:0,99999999.99',

            'discount' => 'required|numeric|min:0',
            'discount_type' => 'required|in:percent,amount',
            'description' => 'nullable|string|max:1000',
            'veg' => 'nullable|boolean',
            'product_code' => 'nullable|string|max:191',

            // Legacy choice options (for backward compatibility)
            'choice' => 'nullable|array',
            'addons_ids' => 'nullable|array|exists:add_ons,id',

            // New variation structure
            'variation_name' => 'nullable|string|max:191',
            'variation_types' => 'nullable|array',
            'variation_details' => 'nullable|array',
            'variation_details.*.variation_id' => 'nullable|string',
            'variation_details.*.attribute_id' => 'nullable|integer',
            'variation_details.*.attribute_value' => 'nullable|string',
            'variation_details.*.cost_price' => 'nullable|numeric|min:0',
            'variation_details.*.sale_price' => 'nullable|numeric|min:0',
            'variation_details.*.quantity' => 'nullable|numeric|min:0',
            'variation_details.*.barcode' => 'nullable|string|max:191',

            // Receipt creation (optional - if provided, receipt will be auto-created)
            'counterparty_id' => 'nullable|integer|exists:counterparties,id',
        ];
    }
}
