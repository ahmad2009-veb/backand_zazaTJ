<?php

namespace App\Http\Requests\Api\v3\admin;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class RestauranUpdateRequest extends FormRequest
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

        $restaurant = $this->route('store');
        $vendorId = $this->route('store')->vendor->id;


        return [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'name' => 'required_if:main_store_id,null|max:191',
            'address' => 'required|max:1000',
//            'main_restaurant_id' => 'nullable|exists:restaurants,id',
            'map_link' => 'nullable|max:1000',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
            'email' => 'nullable|unique:vendors,email,' . $vendorId,
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:20',
            'minimum_delivery_time' => 'required|regex:/^([0-9]{2})$/|min:2|max:2',
            'maximum_delivery_time' => 'required|regex:/^([0-9]{2})$/|min:2|max:2|gt:minimum_delivery_time',
            'password' => 'nullable|min:6',
//            'zone_id' => 'required',
            'logo' => 'nullable|mimes:png,svg,jpg,jpeg',
            'cover_photo' => 'nullable|mimes:jpg,jpeg,png,svg|max:1024',
            'tax' => 'required_if:main_store_id,null',
        ];

    }
}
