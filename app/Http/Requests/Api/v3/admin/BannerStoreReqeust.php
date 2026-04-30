<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class BannerStoreReqeust extends FormRequest
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
            'title'         => 'required|max:191',
            'image'         => 'required',
            'banner_type'   => 'required',
            'zone_id'       => 'required',
            'restaurant_id' => 'required_if:banner_type,restaurant_wise',
            'item_id'       => 'required_if:banner_type,item_wise',
        ];
    }

    public  function messages()
    {
        return [
            'zone_id.required' => trans('messages.select_a_zone'),
            'restaurant_id.required_if' => trans('messages.Restaurant is required when banner type is restaurant wise'),
            'item_id.required_if'       => trans('messages.Food is required when banner type is food wise'),
        ];
    }
}
