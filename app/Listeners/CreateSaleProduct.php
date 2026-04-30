<?php

namespace App\Listeners;

use App\Enums\SaleStatusEnum;
use App\Events\OrderStatusChanged;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateSaleProduct
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(public SaleService $saleService, public TransactionService $transactionService)
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\OrderStatusChanged  $event
     * @return void
     */
    public function handle(OrderStatusChanged $event): void
    {
        $defaultDriver = Auth::getDefaultDriver();

        $guard = match ($defaultDriver) {
            'admin-api'  => 'admin',
            'vendor_api' =>  'vendor',
            default => $defaultDriver,
        };

        $sale = Sale::where('order_id', $event->order->id)->exists();
        $details =   $event->order->details;
        $user = auth()->user();

        if ($event->order->order_status->value === 'successful') {

            if (!$sale) {
                $this->createSale($details, SaleStatusEnum::COMPLETED, $event, $user, $guard);
            }
        } else {
            if (!$sale) {
                $this->createSale($details, SaleStatusEnum::PENDING, $event, $user, $guard);
            }
        }
    }


    private function createSale($details, $status, $event, $user, $guard)
    {
        $data = [
            'products' => []
        ];
        foreach ($details as $detail) {
            $variation = json_decode($detail['variation'], true);
            $data['products'][] = [
                'product_id' => $detail['product_id'],
                'price' => $detail['price'],
                'purchase_price' => json_decode($detail['product_details'])?->purchase_price,
                'variation' => !empty($variation) ? $variation[0] : null,
                'discount' => $detail['discount_on_food'] ?? 0,
                'quantity' => $detail['quantity'],
                'discount_type' => $detail['discount_type']
            ];
            // Note: Inventory deduction is now handled in OrderController
        }
        $name = trim((string)($event->order->customer?->f_name ?? '') . ' ' . (string)($event->order->customer?->phone ?? ''));
        $data['name'] = $name !== '' ? $name : null;
        $data['status'] = $status;
        $data['user_id'] = $event->order->user_id;
        $data['delivery_man_id'] = $event->order->delivery_man_id;
        $data['warehouse_id'] = $event->order->warehouse_id;
        $data['delivery_charge'] = $event->order->delivery_charge;
        $data['order_id'] = $event->order->id;
        $sale = $this->saleService->StoreSale($data);

        // Only create full transaction for non-installment orders
        // For installment orders, transactions are created individually for each payment
        if (!$event->order->orderInstallment) {
            $this->transactionService->saleTransactionStore($event->order->order_amount, $user, $guard, $sale);
        }
    }
}
