<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use App\CentralLogics\CouponLogic;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Arr;

/**
 * Class CalculateTotalAction
 * @package App\Actions\Cart
 */
class CalculateTotalsAction extends BaseAction
{
    public function __construct(
        private readonly CalculateDeliveryAction $calculateDeliveryAction
    ) {
    }

    public function handle(array $cart): array
    {
        $this->calculateTotalPrice($cart);
        $this->calculateDelivery($cart);
        $this->calculateTotal($cart);

        return $cart;
    }

    private function calculateTotalPrice(array &$cart)
    {
        $total = array_sum(array_map(
            fn(array $item) => Arr::get($item, 'form.price', 0),
            Arr::get($cart, 'items', [])
        ));

        $cart['total_price'] = $total;
    }

    private function calculateDelivery(array &$cart)
    {
        $cart['delivery'] = $this->calculateDeliveryAction->execute($cart);
    }

    private function calculateTotal(array &$cart)
    {
        $totalPrices          = Arr::get($cart, 'total_price', 0);
        $deliveryCharge       = $this->getDeliveryCharge($cart);
        $couponDiscountAmount = $this->getCouponDiscountAmount($cart);

        $cart['coupon_discount_amount'] = $couponDiscountAmount;
        $cart['total']                  = ($totalPrices + $deliveryCharge) - $couponDiscountAmount;
    }

    private function getDeliveryCharge(array $cart): float
    {
        $deliveryCharge = Arr::get($cart, 'delivery.charge', 0);

        return Arr::get($cart, 'order_type') === Order::DELIVERY ? $deliveryCharge : 0;
    }

    private function getCouponDiscountAmount(array $cart): float
    {
        $couponDiscountAmount = 0;

        if ($id = Arr::get($cart, 'coupon.id')) {
            $coupon = Coupon::find($id);
            if ($coupon) {
                $totalPrices = Arr::get($cart, 'total_price', 0);

                $couponDiscountAmount = CouponLogic::get_discount($coupon, $totalPrices);
            }
        }

        return $couponDiscountAmount;
    }
}
