<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminRequest extends FormRequest
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
        $admin = $this->route('admin');
        return [
            'f_name'   => 'required',
            'l_name'   => 'nullable|max:100',
            'role_id'  => 'required',
            'image'    => 'nullable|mimes:png,svg,jpg,jpeg',
            'email'    => 'required|unique:admins,email,' . $admin->id,
            'phone'    => 'required|unique:admins,phone,' . $admin->id,
            'password' => 'nullable|min:6',
        ];
    }
}
