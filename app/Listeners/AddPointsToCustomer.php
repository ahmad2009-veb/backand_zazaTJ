<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;


class AddPointsToCustomer
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\OrderPaid  $event
     * @return void
     */
    public function handle(OrderPaid $event)
    {

        if($event->order->payment_status == 'paid') {
            $order = $event->order;
           if(isset($order->customer_point)) {
               Log::debug('sssss');
               $order->customer->loyalty_point += $order->customer_point['points'];
               $order->customer->save();
           }

        }
    }
}
