<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeUpdateRequest extends FormRequest
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
       $adminId =  $this->route('admin');


        return [
            'f_name'  => 'required|max:100',
            'l_name'  => 'nullable|max:100',
            'role_id' => 'required',
            'email'   => 'required|unique:admins,email,' . $adminId,
            'phone'   => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:20|unique:admins,phone,' . $adminId,
        ];
    }
}
