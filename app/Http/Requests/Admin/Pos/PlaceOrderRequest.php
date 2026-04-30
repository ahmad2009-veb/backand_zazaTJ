<?php

namespace App\Http\Requests\Admin\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cart'        => ['required', 'array'],
            'customer_id' => ['required'],
            'address_id'  => ['required'],
        ];
    }
}
