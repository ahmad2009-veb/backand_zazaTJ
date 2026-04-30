<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryManStoreRequest extends FormRequest
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
            'l_name' => 'nullable|max:100',
            'email' => 'required|unique:delivery_men',
            'phone' => 'required|min:10|max:20|unique:delivery_men',
            'identity_number' => 'required|max:30',
            'identity_type' => 'required|in:passport,nid,driving_license',
            'identity_image' => 'required|array',
            'identity_image.*' => 'required|image|max:4096',
            'image' => 'required|image|max:1024',
            'zone_id' => 'nullable',
            'earning' => 'required|boolean',
            'password' => 'required|min:6',
            'store_id' => 'nullable|exists:stores,id',
        ];
    }
}
