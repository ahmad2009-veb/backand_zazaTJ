<?php

namespace App\Http\Requests\Api\v3;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingRequest extends FormRequest
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
            'contact_person_name' => 'required|string|max:255',
            'address_type' => 'required|string|max:255',
            'contact_person_number' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'floor' => 'nullable|string|max:255',
            'house' => 'nullable|string|max:255',
            'road' => 'nullable|string|max:255',
        ];
    }
}
