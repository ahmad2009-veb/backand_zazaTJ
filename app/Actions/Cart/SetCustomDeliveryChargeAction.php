<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use Illuminate\Support\Arr;

class SetCustomDeliveryChargeAction extends BaseAction
{
    public function __construct(
        private readonly GetCartAction $getCartAction,
        private readonly CalculateTotalsAction $calculateTotalsAction
    ) {
    }

    public function handle(float $customDeliveryCharge, string $cartName): array
    {
        $cart = $this->getCartAction->execute($cartName);

        Arr::set($cart, 'custom.delivery.charge', $customDeliveryCharge);

        $cart = $this->calculateTotalsAction->execute($cart);

        session()->put($cartName, $cart);

        return $cart;
    }
}
