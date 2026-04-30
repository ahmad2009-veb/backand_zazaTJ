<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressUpdateRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {

        return [
            'street' => 'required|string|max:255',
            'house' => 'string|nullable',
            'apartment' => 'string|nullable',
            'domofon_code' => 'string|nullable',
            'longitude' => 'nullable',
            'latitude' => 'nullable'
        ];
    }
}

