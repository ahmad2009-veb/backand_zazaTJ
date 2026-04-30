<?php

namespace App\Http\Requests\Api\v3\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
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
            'f_name' => 'required|string|max:100',
            'l_name' => 'nullable|string|max:100',
            'phone' => 'required|string|unique:vendor_employees,phone',
            'password' => 'required|string|min:6',
            'image' => 'nullable|mimes:png,svg,jpg,jpeg|max:2048',
            'modules' => 'required|array|min:1',
            'modules.*' => 'string|in:pos-terminal,orders,chats,warehouse,finance,couriers,customers',
        ];
    }

    public function messages()
    {
        return [
            'phone.regex' => 'Phone number must start with +992 and be followed by 9 digits.',
            'modules.required' => 'Please select at least one module/page access.',
            'modules.*.in' => 'Invalid module selected.',
        ];
    }


}
