<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use App\Models\Coupon;
use App\Models\Restaurant;

class GetRestaurantDiscountAmountAction extends BaseAction
{
    public function handle(array $cart, Restaurant $restaurant): float
    {
        return 0;
    }
}
