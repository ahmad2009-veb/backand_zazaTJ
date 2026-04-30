<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;

class NotificationRequest extends FormRequest
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
            'title' => 'required|string|max:50',
            'description' => 'required|string|max:256',
            'tergat' => 'nullable',
            'image' => 'nullable|image|max:1024',
            'zone_id' => 'nullable|numeric'
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'A Title is  required',
            'image.uploaded' => 'file size is too bog'
        ];
    }
}
