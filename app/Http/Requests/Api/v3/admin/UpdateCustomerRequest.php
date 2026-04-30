<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
            'f_name' => 'required|max:100',
            'l_name' => 'required| max: 100',
            'phone' => 'required|string|size:13|unique:users,phone',
            'address' => 'nullable|array',
            'addresses.*.road' => 'nullable|string',
            'addresses.*.street' => 'nullable|string',
            'addresses.*.house' => 'nullable|numeric',
            'addresses.*.domofon_code' => 'nullable|numeric',
            'addresses.*.apartment' => 'nullable|numeric',
            'addresses.*.latitude' => 'nullable|numeric',
            'addresses.*.longitude' => 'nullable|numeric',
        ];
    }
}
