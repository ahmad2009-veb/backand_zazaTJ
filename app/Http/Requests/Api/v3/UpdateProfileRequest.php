<?php

namespace App\Http\Requests\Api\v3;

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
     * @return array
     */
    public function rules()
    {
        $user = auth()->user();
        return [
            'name' => 'required|string|max:50',
            'birth_date' => 'nullable|string',
            'phone' => 'required|min:13|max:13|unique:users,phone,' . $user->id,
            'password' => 'nullable|min:8',
            'image' => 'nullable|image|mimes:jpg,png,webp|max:2048'
        ];
    }
}
