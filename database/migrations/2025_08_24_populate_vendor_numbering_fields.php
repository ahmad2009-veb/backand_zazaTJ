<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Event;
use App\Models\VendorEmployee;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Transaction;
use App\Models\DeliveryMan;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Disable all model events during migration to prevent listener issues
        Event::fake();

        // Populate numbering for existing records using Eloquent models
        $this->populateVendorEmployeeNumbers();
        $this->populateOrderNumbers();
        $this->populateStoreNumbers();
        $this->populateUserNumbers();
        $this->populateTransactionNumbers();
        $this->populateDeliveryManNumbers();
    }

    private function populateVendorEmployeeNumbers()
    {
        $vendorIds = VendorEmployee::distinct()->pluck('vendor_id');

        foreach ($vendorIds as $vendorId) {
            $employees = VendorEmployee::where('vendor_id', $vendorId)
                                     ->orderBy('id')
                                     ->get();

            foreach ($employees as $index => $employee) {
                $employee->update(['employee_number' => $index + 1]);
            }
        }
    }

    private function populateOrderNumbers()
    {
        // Orders use store_id, group by store_id
        $storeIds = Order::distinct()->whereNotNull('store_id')->pluck('store_id');

        foreach ($storeIds as $storeId) {
            $orders = Order::where('store_id', $storeId)
                          ->orderBy('id')
                          ->get();

            foreach ($orders as $index => $order) {
                $order->update(['order_number' => $index + 1]);
            }
        }
    }

    private function populateStoreNumbers()
    {
        $vendorIds = Store::distinct()->pluck('vendor_id');

        foreach ($vendorIds as $vendorId) {
            $stores = Store::where('vendor_id', $vendorId)
                          ->orderBy('id')
                          ->get();

            foreach ($stores as $index => $store) {
                $store->update(['store_number' => $index + 1]);
            }
        }
    }

    private function populateUserNumbers()
    {
        // Users use created_by which references stores, group by created_by (store_id)
        $storeIds = User::distinct()->whereNotNull('created_by')->pluck('created_by');

        foreach ($storeIds as $storeId) {
            $users = User::where('created_by', $storeId)
                        ->orderBy('id')
                        ->get();

            foreach ($users as $index => $user) {
                $user->update(['user_number' => $index + 1]);
            }
        }
    }

    private function populateTransactionNumbers()
    {
        $vendorIds = Transaction::distinct()->whereNotNull('vendor_id')->pluck('vendor_id');

        foreach ($vendorIds as $vendorId) {
            $transactions = Transaction::where('vendor_id', $vendorId)
                                     ->orderBy('id')
                                     ->get();

            foreach ($transactions as $index => $transaction) {
                $transaction->update(['transaction_number' => $index + 1]);
            }
        }
    }

    private function populateDeliveryManNumbers()
    {
        $storeIds = DeliveryMan::distinct()->whereNotNull('store_id')->pluck('store_id');

        foreach ($storeIds as $storeId) {
            $deliveryMen = DeliveryMan::where('store_id', $storeId)
                                    ->orderBy('id')
                                    ->get();

            foreach ($deliveryMen as $index => $deliveryMan) {
                $deliveryMan->update(['delivery_man_number' => $index + 1]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Disable events and reset all numbering fields to null using Eloquent
        Event::fake();

        VendorEmployee::query()->update(['employee_number' => null]);
        Order::query()->update(['order_number' => null]);
        Store::query()->update(['store_number' => null]);
        User::query()->update(['user_number' => null]);
        Transaction::query()->update(['transaction_number' => null]);
        DeliveryMan::query()->update(['delivery_man_number' => null]);
    }
};
