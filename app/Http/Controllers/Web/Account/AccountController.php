<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;

class AccountController extends Controller
{
    public function index()
    {
        return view('web.account.index');
    }

    public function profile()
    {
        return view('web.account.profile.index');
    }

    public function addresses()
    {
        return view('web.account.addresses.index');
    }

    public function orders()
    {
        return view('web.account.orders.index');
    }
}
