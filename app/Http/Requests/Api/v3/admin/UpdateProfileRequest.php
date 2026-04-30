<?php

namespace App\Http\Requests\Api\V3\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
        $adminId = auth()->id();

        return [
            'f_name' => 'required|string|max:100',
            'l_name' => 'nullable|string|max:100',
            'image'  => 'nullable|mimes:png,svg,jpg,jpeg',
            'email'  => "nullable|email|unique:admins,email,{$adminId}",
            'phone'  => "nullable|unique:admins,phone,{$adminId}",
            'password' => 'nullable|string|min:6',
        ];
    }
}
