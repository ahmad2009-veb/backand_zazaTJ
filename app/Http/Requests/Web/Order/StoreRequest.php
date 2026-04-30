<?php

namespace App\Http\Requests\Web\Order;

use App\Models\Restaurant;
use App\Models\Zone;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->session()->has('cart');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'address_id'     => ['required', 'exists:customer_addresses,id'],
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['nullable', 'email'],
            'phone'          => ['required', 'string', 'regex:/^\+992\d{9}$/'],
            'notes'          => ['nullable', 'string'],
            'payment_method' => ['required', 'in:by_card,cash_on_delivery'],
        ];
    }

    /**
     * @param $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $restaurantId = $this->session()->get('cart.restaurant_id');
            $scheduleAt   = now();

            $restaurant = Restaurant::with('discount')
                ->selectRaw('*, IF(
                    (
                        (
                            select count(*) from `restaurant_schedule`
                            where `restaurants`.`id` = `restaurant_schedule`.`restaurant_id`
                               and `restaurant_schedule`.`day` = ' . $scheduleAt->format('w') . '
                               and `restaurant_schedule`.`opening_time` < "' . $scheduleAt->format('H:i:s') . '"
                               and `restaurant_schedule`.`closing_time` >"' . $scheduleAt->format('H:i:s') . '"
                        ) > 0
                    ), true, false) as open')
                ->where('id', $restaurantId)
                ->first();

            if ($restaurant->open == false) {
                $validator->errors()->add('order', 'Заказ в данном ресторане не доступен');
            }

            if ($this->latitude && $this->longitude) {
                $point = new Point($this->latitude, $this->longitude);
                $zone  = Zone::where('id', $restaurant->zone_id)->contains('coordinates', $point)->first();

                if (!$zone) {
                    $validator->errors()->add('order', 'Сервис не доступен в этой области');
                } else {
                    if ($zone->per_km_shipping_charge && $zone->minimum_shipping_charge) {
                        $this->merge([
                            'per_km_shipping_charge'  => $zone->per_km_shipping_charge,
                            'minimum_shipping_charge' => $zone->minimum_shipping_charge,
                        ]);
                    }
                }
            }
        });
    }
}
