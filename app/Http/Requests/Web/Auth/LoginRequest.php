<?php

namespace App\Http\Requests\Web\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'phone' => [
                'bail',
                'required',
                'string',
                'regex:/^(90|91|92|93|94|97|98|99|17|71|50|55|60|88|70|77|11|00|40|80|10|20|22|30)[\d]{7}$/',
            ],
        ];
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Укажите телефон',
            'phone.regex'    => 'Правильно введите номер телефона',
        ];
    }
}
