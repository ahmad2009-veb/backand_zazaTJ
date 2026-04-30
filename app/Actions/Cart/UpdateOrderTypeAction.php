<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;

class UpdateOrderTypeAction extends BaseAction
{
    public function __construct(
        private readonly GetCartAction $getCartAction,
        private readonly CalculateTotalsAction $calculateTotalsAction
    ) {
    }

    public function handle(string $orderType, string $cartName): array
    {
        $cart = $this->getCartAction->execute($cartName);

        $cart['order_type'] = $orderType;

        $cart = $this->calculateTotalsAction->execute($cart);

        session()->put('cart', $cart);

        return $cart;
    }
}
