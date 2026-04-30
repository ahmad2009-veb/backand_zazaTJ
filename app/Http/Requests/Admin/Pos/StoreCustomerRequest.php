<?php

namespace App\Http\Requests\Admin\Pos;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'f_name' => ['required', 'string', 'max:255'],
            'l_name' => ['nullable', 'string', 'max:255'],
            'email'  => ['nullable', 'email', 'unique:users'],
            'phone'  => [
                'bail',
                'required',
                'string',
                'regex:/^\+992[\d]{9}$/',
            ],
        ];
    }

    public function messages()
    {
        return [
            'f_name.required' => 'Укажите имя',
            'l_name.required' => 'Укажите фамилию',
            'email.email'     => 'Правильно укажите email',
            'email.unique'    => 'Такой email уже используется',
            'phone.required'  => 'Укажите телефон',
            'phone.regex'     => 'Правильно введите номер телефона',
        ];
    }

    public function withValidator($validator)
    {
        $phone = $this->phone;

        $validator->after(function ($validator) use ($phone) {
            if (User::where('phone', $phone)->exists()) {
                $validator->errors()->add('phone', 'Такой телефон уже используется');
            }
        });
    }
}
