<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use App\Data\Product\ProductData;
use App\Models\Order;
use Illuminate\Support\Arr;

/**
 * Class CreateCartItemAction
 * @package App\Actions\Cart
 */
class CreateCartItemAction extends BaseAction
{
    public function __construct(
        private readonly GetCartAction $getCartAction,
        private readonly CalculateTotalsAction $calculateTotalsAction,
        private readonly CalculateDeliveryAction $calculateDeliveryAction
    ) {
    }

    public function handle(array $data, string $cartName): array
    {
        $cart = $this->getCartAction->execute($cartName);

        if (empty($cart['restaurant'])) {
            $this->setRestaurant($cart, $data);
        }

        if (empty($cart['order_type'])) {
            $this->setDefaultOrderType($cart);
        }

        $productData = ProductData::fromArray(Arr::get($data, 'product'));

        $cart['items'][] = [
            'id'      => uniqid(),
            'form'    => [
                'quantity' => Arr::get($data, 'quantity', 1),
                'options'  => Arr::get($data, 'options', []),
                'extra'    => $this->getExtra($productData, $data),
                'price'    => get_product_calculated_price($productData, $data),
            ],
            'product' => $productData->toArray(),
        ];

        $cart = $this->calculateTotalsAction->execute($cart);

        session()->put($cartName, $cart);

        return $cart;
    }

    private function setRestaurant(array &$cart, array $data)
    {
        $cart['restaurant'] = [
            'id'   => Arr::get($data, 'product.restaurant_id'),
            'name' => Arr::get($data, 'product.restaurant_name'),
            'slug' => Arr::get($data, 'product.restaurant_slug'),
        ];
    }

    private function setDefaultOrderType(array &$cart)
    {
        $cart['order_type'] = Order::DELIVERY;
    }

    /**
     * @param \App\Data\Product\ProductData $productData
     * @param array $data
     * @return array
     */
    private function getExtra(ProductData $productData, array $data): array
    {
        $extra  = Arr::get($data, 'extra', []);
        $addOns = collect($productData->extra);

        return array_map(function ($item) use ($addOns) {
            $id    = Arr::get($item, 'id');
            $addOn = $addOns->where('id', $id)->first();

            return array_merge($item, [
                'name'  => Arr::get($addOn, 'name'),
                'price' => Arr::get($addOn, 'price'),
            ]);
        }, $extra);
    }
}
