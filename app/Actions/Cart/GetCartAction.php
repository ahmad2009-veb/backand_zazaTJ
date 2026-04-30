<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;

class GetCartAction extends BaseAction
{
    public function handle(string $cartName): array
    {
        return session()->get($cartName, []);
    }
}
