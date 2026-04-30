<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;
use Illuminate\Support\Arr;

/**
 * Class CalculateTotalAction
 * @package App\Actions\Cart
 */
class CalculateTotalAction extends BaseAction
{
    /**
     * @param array $cart
     * @return float
     */
    public function handle(array $cart): float
    {
        $items = Arr::get($cart, 'items', []);

        return array_sum(array_map(
            fn(array $item) => Arr::get($item, 'form.price', 0),
            $items
        ));
    }
}
