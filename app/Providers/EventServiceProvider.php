<?php

namespace App\Providers;

use App\Events\CustomerPointStatus;
use App\Events\OrderPaid;
use App\Events\OrderStatusChanged;
use App\Events\ProductSold;
use App\Listeners\AddPointsToCustomer;
use App\Listeners\CreateSaleProduct;
use App\Listeners\MakeCustomerPointStatus;
use App\Listeners\ChangeSaleStatus;
use App\Listeners\UpdateProductProfitability;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        OrderPaid::class => [
            AddPointsToCustomer::class,
        ],

        CustomerPointStatus::class => [
            MakeCustomerPointStatus::class
        ],
        ProductSold::class => [
            UpdateProductProfitability::class,
        ],
        OrderStatusChanged::class => [
            CreateSaleProduct::class,
            ChangeSaleStatus::class
        ]

    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        //
    }
}
