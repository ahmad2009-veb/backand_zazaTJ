<?php

namespace App\Http\Requests\Api\v3\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\CounterpartyTypeEnum;

class CounterpartyStoreRequest extends FormRequest
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
            'counterparty' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'requisite' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'type' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Check if it's a valid enum value
                    $enumValues = CounterpartyTypeEnum::values();
                    if (in_array($value, $enumValues)) {
                        return; // Valid enum type
                    }
                    
                    // If not an enum value, it should be a custom type name
                    // We'll validate this in the controller to check vendor ownership
                }
            ],
            'balance' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'counterparty.required' => 'Поле "Контрагент" обязательно для заполнения.',
            'counterparty.string' => 'Поле "Контрагент" должно быть строкой.',
            'counterparty.max' => 'Поле "Контрагент" не должно превышать 255 символов.',

            'name.required' => 'Поле "Наименование" обязательно для заполнения.',
            'name.string' => 'Поле "Наименование" должно быть строкой.',
            'name.max' => 'Поле "Наименование" не должно превышать 255 символов.',

            'address.string' => 'Поле "Адрес" должно быть строкой.',
            'address.max' => 'Поле "Адрес" не должно превышать 500 символов.',

            'requisite.string' => 'Поле "Реквизиты" должно быть строкой.',
            'requisite.max' => 'Поле "Реквизиты" не должно превышать 500 символов.',

            'phone.string' => 'Поле "Телефон" должно быть строкой.',
            'phone.max' => 'Поле "Телефон" не должно превышать 20 символов.',

            'type.required' => 'Поле "Тип" обязательно для заполнения.',
            'type.in' => 'Выбранный тип недействителен.',

            'balance.numeric' => 'Поле "Баланс" должно быть числом.',
            'balance.min' => 'Поле "Баланс" не может быть отрицательным.',

            'notes.string' => 'Поле "Примечания" должно быть строкой.',
            'notes.max' => 'Поле "Примечания" не должно превышать 1000 символов.',

            'status.in' => 'Выбранный статус недействителен.',

            'photo.image' => 'Файл должен быть изображением.',
            'photo.mimes' => 'Изображение должно быть в формате: jpeg, png, jpg, gif.',
            'photo.max' => 'Размер изображения не должен превышать 2MB.',
        ];
    }
}
