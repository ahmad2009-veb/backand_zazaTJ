<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeStoreRequest extends FormRequest
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
            'f_name'   => 'required',
            'l_name'   => 'nullable|max:100',
            'role_id'  => 'required',
            'image'    => 'required|mimes:png,svg,jpg,jpeg',
            'email'    => 'required|unique:admins',
            'phone'    => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:20|unique:admins',
            'password' => 'required|min:6',
        ];
    }
}
