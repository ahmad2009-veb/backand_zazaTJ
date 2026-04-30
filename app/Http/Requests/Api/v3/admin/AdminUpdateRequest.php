<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateRequest extends FormRequest
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
            'f_name' => 'string|required',
            'l_name' => 'email|required',
            'email' => 'email|required|unique:users,email,' . auth()->user()->id,
            'phone' => 'string|required',
            'password' => 'string|nullable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:1024',
        ];
    }
}
