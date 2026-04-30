<?php

namespace App\Actions\Cart;

use App\Actions\BaseAction;

class ForgetCartAction extends BaseAction
{
    public function handle(string $cartName)
    {
        session()->put($cartName, []);
    }
}
