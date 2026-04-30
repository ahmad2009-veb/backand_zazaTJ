<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use Illuminate\Support\Arr;

/**
 * Class RemoveCartItemAction
 * @package App\Actions\Cart
 */
class RemoveCartItemAction extends BaseAction
{
    public function __construct(
        private readonly GetCartAction $getCartAction,
        private readonly CalculateTotalsAction $calculateTotalsAction
    ) {
    }

    public function handle(string $id, string $cartName): array
    {
        $cart = $this->getCartAction->execute($cartName);

        $items = Arr::get($cart, 'items', []);

        $cart['items'] = array_filter($items, fn(array $item) => Arr::get($item, 'id') != $id);

        $cart = $this->calculateTotalsAction->execute($cart);

        if (empty($cart['items'])) {
            $cart = [];
        }

        session()->put($cartName, $cart);

        return $cart;
    }
}
