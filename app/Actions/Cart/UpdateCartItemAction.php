<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use App\Data\Product\ProductData;
use Illuminate\Support\Arr;

/**
 * Class UpdateCartItemAction
 * @package App\Actions\Cart
 */
class UpdateCartItemAction extends BaseAction
{
    public function __construct(
        private readonly GetCartAction $getCartAction,
        private readonly CalculateTotalsAction $calculateTotalsAction
    ) {
    }

    public function handle(string $id, array $data, string $cartName): array
    {
        $cart = $this->getCartAction->execute($cartName);

        $items = Arr::get($cart, 'items', []);

        $key  = array_search($id, array_column($items, 'id'));
        $item = $items[$key];

        $productData = ProductData::fromArray(
            Arr::get($item, 'product')
        );

        $items[$key]['form'] = array_merge($data, [
            'price' => get_product_calculated_price($productData, $data),
        ]);

        $cart['items'] = $items;

        $cart = $this->calculateTotalsAction->execute($cart);

        session()->put($cartName, $cart);

        return $cart;
    }
}
