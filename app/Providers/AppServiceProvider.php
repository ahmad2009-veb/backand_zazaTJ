<?php

namespace App\Providers;

ini_set('memory_limit', '-1');

use App\CentralLogics\Helpers;
use App\Models\AdminToken;
use App\Services\SaleService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(SaleService::class, function () {
            return new SaleService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        try {
            Paginator::useBootstrap();
            foreach (Helpers::get_view_keys() as $key => $value) {
                view()->share($key, $value);
            }
        } catch (\Exception $e) {
        }

        View::composer('*', function ($view) {
            $view->with('currentUser', Auth::user()?->only('f_name', 'l_name', 'phone', 'email'));
        });
    }
}
