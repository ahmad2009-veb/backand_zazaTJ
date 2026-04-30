<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;

class CampaignStoreRequest extends FormRequest
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
            'title' => 'required|string',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:1024',
            'status' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'campaign_rule_id' => 'nullable|integer|exists:campaign_rules,id',

            'rule_type' => 'required|string|in:combo,total_order,complete_campaigns',
            'criteria_bonus' => 'required',
            'criteria_total_amount' => 'required_if:rule_type,total_order|prohibited_if:rule_type,combo',
            'restaurant_ids' => 'required|array',
            'restaurant_ids.*' => 'required|int',
        ];
    }
}
