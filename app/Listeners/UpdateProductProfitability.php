<?php

namespace App\Listeners;

use App\Events\ProductSold;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdateProductProfitability
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(public SaleService $saleService) {}

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(ProductSold $event)
    {


        $this->saleService->UpdateProductsProfitability($event->sale);
    }
}
