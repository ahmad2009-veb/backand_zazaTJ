<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use App\Models\BusinessSetting;
use App\Models\Restaurant;
use Illuminate\Support\Arr;

class CalculateDeliveryAction extends BaseAction
{
    private float $charge = 0;

    private bool $is_free = false;

    public function handle(array $cart): array
    {
        $this->calculateDeliveryCharge($cart);

        return [
            'charge'  => $this->charge,
            'is_free' => $this->is_free,
        ];
    }

    private function calculateDeliveryCharge(array $cart)
    {
        $this->is_free = false;
        $this->charge  = 0;

        if (Arr::get($cart, 'coupon.coupon_type') === 'free_delivery') {
            $this->is_free = true;

            return;
        }

        if (Arr::get($cart, 'order_type') === 'take_away') {
            $this->is_free = true;

            return;
        }

        $customDeliveryCharge = Arr::get($cart, 'custom.delivery.charge', 0);
        if ($customDeliveryCharge) {
            $this->charge = $customDeliveryCharge;

            return;
        }

        $minimum_shipping_charge = BusinessSetting::where('key', 'minimum_shipping_charge')->first()->value;
        if ($minimum_shipping_charge) {
            $this->charge = $minimum_shipping_charge;
        }

        $free_delivery_over = BusinessSetting::where('key', 'free_delivery_over')->first()->value;
        if ($free_delivery_over && $free_delivery_over <= $cart['total_price']) {
            $this->charge  = 0;
            $this->is_free = true;
        }

        $restaurant = Restaurant::find($cart['restaurant']['id']);
        if ($restaurant && $restaurant->free_delivery) {
            $this->charge = 0;
        }
    }
}
