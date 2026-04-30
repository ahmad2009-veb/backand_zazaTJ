<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantStoreRequest extends FormRequest
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
     * @return string[]
     */
    public function rules()
    {
        return [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'name' => 'required_if:main_store_id,null|max:191', 
            'address' => 'required|max:1000', 
            'map_link' => 'nullable|string|max:1000',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
//            'main_restaurant_id' => 'nullable|exists:restaurants,id',
            'email' => 'nullable|unique:vendors', 
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:20|unique:vendors,phone', 
            'minimum_delivery_time' => 'nullable|regex:/^([0-9]{2})$/|min:2|max:2',
            'maximum_delivery_time' => 'nullable|regex:/^([0-9]{2})$/|min:2|max:2|gt:minimum_delivery_time',
            'password' => 'required|min:6',
//            'zone_id' => 'required',
            'logo' => 'nullable:main_store_id,null|mimes:png,svg,jpg,jpeg',
            'cover_photo' => 'nullable:main_store_id,null|mimes:jpg,jpeg,png,svg|max:1024',
            'tax' => 'nullable:main_store_id,null',
        ];
    }
}
