<?php

namespace App\Actions\Cart\Traits;

use App\Actions\Cart\CalculateDeliveryAction;
use App\Http\Livewire\Order;
use Illuminate\Support\Arr;

trait HasDelivery
{
//    public function updateDelivery(array &$cart)
//    {
//        if (Arr::get($cart, 'order_type') === Order::DELIVERY) {
//            $cart['total'] += $cart['delivery']['charge'];
//        }
//    }
//
//    public function setFreeDelivery(array &$cart)
//    {
//        $cart['delivery'] = [
//            'charge'  => 0,
//            'is_free' => true,
//        ];
//    }
//
//    public function calculateDilivery(array &$cart)
//    {
//        $cart['delivery'] = app(CalculateDeliveryAction::class)->execute($cart);
//    }
}
