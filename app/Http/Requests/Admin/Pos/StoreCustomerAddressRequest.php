<?php

namespace App\Http\Requests\Admin\Pos;

use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use MatanYadaev\EloquentSpatial\Objects\Point;

class StoreCustomerAddressRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'latitude'  => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'road'      => ['required', 'string', 'max:255'],
            'house'     => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'road.required' => 'Укажите улицу',
        ];
    }

    public function withValidator($validator)
    {
        $point = new Point($this->latitude, $this->longitude);
        $zones = Zone::whereContains('coordinates', $point)->get(['id']);

        $validator->after(function ($validator) use ($zones) {
            if (count($zones) == 0) {
                $validator->errors()->add('road', 'Сервис не доступен в этой области');
            }
        });
    }
}
