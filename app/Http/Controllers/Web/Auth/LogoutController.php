<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Livewire\Traits\CartTrait;

class LogoutController extends Controller
{
    use CartTrait;

    public function logout()
    {
        auth()->logout();

        $this->forgetCart();

        return redirect()->route('login');
    }
}
