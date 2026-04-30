<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;

class CampaignRuleStoreRequest extends FormRequest
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
            'rule_type' => 'required|string|in:combo,total_order,complete_campaigns',
            'criteria' => 'required|array',
            'criteria.bonus' => 'required',
            'criteria.total_amount' =>'required_if:rule_type,total_order',
            'description' => 'required|string',
        ];
    }
}
