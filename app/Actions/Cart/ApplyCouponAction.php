<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use App\Models\Coupon;

class ApplyCouponAction extends BaseAction
{
    public function __construct(
        private readonly GetCartAction $getCartAction,
        private readonly CalculateTotalsAction $calculateTotalsAction
    ) {
    }

    public function handle(Coupon $coupon, string $cartName): array
    {
        $cart = $this->getCartAction->execute($cartName);

        $cart = array_merge($cart, [
            'coupon' => [
                'id'          => $coupon->id,
                'code'        => $coupon->code,
                'coupon_type' => $coupon->coupon_type,
            ],
        ]);

        $cart = $this->calculateTotalsAction->execute($cart);

        session()->put('cart', $cart);

        return $cart;
    }
}
