<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            // For API requests, return null - the parent class will throw
            // AuthenticationException which is handled in Handler.php
            return null;
        }

        if ($request->is('admin/*')) {
            return route('admin.auth.login');
        }

        if ($request->is('vendor/*')) {
            return route('vendor.auth.login');
        }

        // Store previous url because authentication flow have several pages
        $request->session()->put('auth.previous.url', url()->current());

        return route('login');
    }
}
