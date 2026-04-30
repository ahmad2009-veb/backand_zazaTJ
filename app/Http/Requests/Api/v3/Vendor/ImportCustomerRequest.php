<?php

namespace App\Http\Requests\Api\v3\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class ImportCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240' // 10MB max
            ]
        ];
    }

    public function messages()
    {
        return [
            'file.required' => 'Файл обязателен для загрузки',
            'file.file' => 'Загруженный файл должен быть файлом',
            'file.mimes' => 'Файл должен быть в формате Excel (xlsx, xls) или CSV',
            'file.max' => 'Размер файла не должен превышать 10MB',
        ];
    }
}
