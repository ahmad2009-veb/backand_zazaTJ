<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use App\CentralLogics\CouponLogic;
use App\CentralLogics\Helpers;
use App\Models\Coupon;
use App\Models\Restaurant;

class GetCouponDiscountAmountAction extends BaseAction
{
    public function __construct(
        private readonly GetTotalProductsPriceAction $totalProductsPriceAction,
        private readonly GetTotalAddonsPriceAction $totalAddonsPriceAction,
        private readonly GetRestaurantDiscountAmountAction $restaurantDiscountAmountAction
    ) {
    }

    public function handle(array $cart, Coupon $coupon): float
    {
        $restaurant                 = Restaurant::with('discount')->firstWhere('id', $cart['restaurant']['id']);
        $restaurant_discount        = Helpers::get_restaurant_discount($restaurant);
        $restaurant_discount_amount = $this->restaurantDiscountAmountAction->execute($cart, $restaurant);

        $product_price     = $this->totalProductsPriceAction->execute($cart);
        $total_addon_price = $this->totalAddonsPriceAction->execute($cart);

        if ($restaurant_discount) {
            if ($product_price + $total_addon_price < $restaurant_discount['min_purchase']) {
                $restaurant_discount_amount = 0;
            }

            if ($restaurant_discount_amount > $restaurant_discount['max_discount']) {
                $restaurant_discount_amount = $restaurant_discount['max_discount'];
            }
        }

        return CouponLogic::get_discount(
            $coupon, $product_price + $total_addon_price - $restaurant_discount_amount
        );
    }
}
