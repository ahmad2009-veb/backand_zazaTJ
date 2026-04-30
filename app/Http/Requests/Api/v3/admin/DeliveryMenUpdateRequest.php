<?php

namespace App\Http\Requests\Api\v3\admin;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryMenUpdateRequest extends FormRequest
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
        $courier = $this->route('deliveryMan');
        return [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'email' => 'required|unique:delivery_men,email,' . $courier->id,
            'phone' => 'required|min:10|max:20|unique:delivery_men,phone,' . $courier->id,
            'identity_number' => 'required|max:30,unique:delivery_men,identity_number,' . $courier->id,
            'identity_type' => 'required|in:passport,nid,driving_license',
            'identity_image' => 'nullable|array',
            'identity_image.*' => 'required|image|max:4096',
            'image' => 'nullable|image|max:1024',
            'zone_id' => 'nullable',
            'earning' => 'required|boolean',
            'password' => 'nullable|min:6',
            'store_id' => 'nullable|exists:stores,id',
        ];
    }
}
