<?php

namespace App\Http\Requests\Api\v3\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class VendorStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:stores,name'],
            'address' => ['required', 'string'],
            'f_name' => ['required', 'string'],
            'phone' => ['required', 'numeric', 'unique:vendors,phone'],
            'email' => ['required', 'email', 'unique:vendors,email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
