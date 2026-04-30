<?php

namespace App\Listeners;

use App\Enums\SaleStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Events\OrderStatusChanged;
use App\Services\TransactionService;
use App\Models\VendorWalletTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChangeSaleStatus
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(public TransactionService $transactionService)
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

        $order = $event->order;
        $sale = $order->sale;
        $saleTransaction = $sale?->transaction;
        $user = auth()->user();
        $defaultDriver = Auth::getDefaultDriver();

        $guard = match ($defaultDriver) {
            'admin-api'  => 'admin',
            'vendor_api' =>  'vendor',
            default => $defaultDriver,
        };

        if ($order->order_status->value === 'successful') {
            $sale->status = SaleStatusEnum::COMPLETED;
            $order->sale->save();

            // For installment orders, don't create a full transaction since individual payments already created transactions
            if ($order->orderInstallment) {
                // For installment orders, just mark any remaining pending wallet transactions as successful
                $vendorId = $order->store?->vendor_id;
                if ($vendorId) {
                    // Get pending wallet transactions that don't have transaction_id (remaining balance)
                    $pendingWalletTransactions = VendorWalletTransaction::where('vendor_id', $vendorId)
                        ->where('order_id', $order->id)
                        ->whereNull('transaction_id')
                        ->where('status', 'pending')
                        ->get();

                    foreach ($pendingWalletTransactions as $walletTx) {
                        // Create individual transaction for each remaining balance payment
                        $category = \App\Models\TransactionCategory::firstOrCreate(
                            ['vendor_id' => $vendorId, 'name' => 'Реализация'],
                            ['parent_id' => 0]
                        );

                        $remainingTransaction = \App\Models\Transaction::create([
                            'name' => 'Завершение рассрочки по заказу #' . $order->id,
                            'amount' => $walletTx->amount,
                            'transaction_category_id' => $category->id,
                            'description' => 'Завершение оплаты рассрочки по заказу #' . $order->id,
                            'type' => \App\Enums\TransactionTypeEnum::INCOME,
                            'vendor_id' => $vendorId,
                            'status' => \App\Enums\TransactionStatusEnum::SUCCESS
                        ]);

                        // Link wallet transaction to the new transaction
                        $walletTx->update([
                            'transaction_id' => $remainingTransaction->id,
                            'status' => 'success',
                            'paid_at' => now(),
                        ]);
                    }
                }
            } else {
                // For regular orders, create the full transaction as before
                if (!$saleTransaction) {
                    $saleTransaction = $this->transactionService->saleTransactionStore($order->order_amount, $user, $guard, $sale);
                }
                $saleTransaction->status = TransactionStatusEnum::SUCCESS;
                $saleTransaction->save();

                // Link any pre-recorded wallet splits for this order to the created transaction
                $vendorId = $order->store?->vendor_id;
                if ($vendorId && $saleTransaction) {
                    VendorWalletTransaction::where('vendor_id', $vendorId)
                        ->where('order_id', $order->id)
                        ->whereNull('transaction_id')
                        ->update([
                            'transaction_id' => $saleTransaction->id,
                        ]);

                    // Ensure pending rows are marked as paid
                    VendorWalletTransaction::where('vendor_id', $vendorId)
                        ->where('order_id', $order->id)
                        ->where(function ($q) {
                            $q->whereNull('status')->orWhereIn('status', ['pending', 'initiated']);
                        })
                        ->update([
                            'status' => 'success',
                            'paid_at' => now(),
                        ]);
                }
            }
        } elseif($order->order_status->value === 'refunded') {
            $sale->status = SaleStatusEnum::REFUNDED;
            $order->sale->save();
            if (!$saleTransaction) {
                $saleTransaction = $this->transactionService->saleTransactionStore($order->order_amount, $user, $guard, $sale);
            }
            $saleTransaction->status = TransactionStatusEnum::SUCCESS;
            $saleTransaction->save();
        } else {
            $sale->status = SaleStatusEnum::PENDING;
            $order->sale->save();

            if (!$saleTransaction) {
                $saleTransaction = $this->transactionService->saleTransactionStore($order->order_amount, $user, $guard, $sale);
            }


            $saleTransaction->status = TransactionStatusEnum::CANCELLED;
            $saleTransaction->save();
        }
    }
}
