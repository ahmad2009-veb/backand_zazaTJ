<?php

namespace App\Http\Requests\Web\Cart;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:1'],
            'options' => ['nullable', 'array'],
            'extra' => ['nullable', 'array'],
            'extra.*.id' => ['required', 'exists:add_ons,id'],
            'extra.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->session()->has('cart.restaurant_id') && $this->restaurant->id != $this->session()->get('cart.restaurant_id')) {
                $validator->errors()->add('restaurant', 'Вы не можете делать заказ из разных ресторанов!');
            }
        });
    }
}
