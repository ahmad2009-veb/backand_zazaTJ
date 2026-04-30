<?php

namespace App\Http\Requests\Api\v3\Cashbox;

use Illuminate\Foundation\Http\FormRequest;

class OrderRefundRequest extends FormRequest
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
            'items' => 'required|array',
            'items.*.id' => 'required|exists:food,id',
            'items.*.qty' => 'required|numeric',
            'items.*.variation' => 'nullable|string',
            'items.*.discount' => 'nullable|numeric',
            'items.*.discount_type' => 'required_with:items.*.discount|in:percent,fixed',
            'items.*.add_ons' => 'nullable|array',
            'items.*.add_ons.*.id' => 'required_with:items.*.add_ons|numeric',
            'items.*.add_ons.*.qty' => 'required_with:items.*.add_ons|numeric',
            //fd_data
            'fd_data' => 'required|array',
            'fd_data.fpd' => 'required|string',
            'fd_data.fdNumber' => 'required|integer',
            'fd_data.shiftNumber' => 'required|integer',
            'fd_data.onlineStatus' => 'required|boolean',
            'fd_data.receiptNumber' => 'required|integer',
        ];
    }
}
