<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignItemsRequest extends FormRequest
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
            'items.*.variant' => 'nullable|string',
            'items.*.add_ons' => 'nullable|array',
            'items.*.add_ons.*' => 'required_with:items.*.add_ons|numeric',
        ];
    }
}
