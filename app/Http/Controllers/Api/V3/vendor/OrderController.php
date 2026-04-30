<?php

namespace App\Http\Controllers\Api\V3\vendor;

use Carbon\Carbon;
use App\Models\Food;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Transaction;
use App\Models\DeliveryMan;
use App\Models\VendorWallet;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Enums\OrderStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use App\Services\OrderService;
use App\Models\BusinessSetting;
use App\Scopes\RestaurantScope;

use App\Services\FinanceService;
use App\CentralLogics\OrderLogic;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use App\Services\RestaurantService;
use Illuminate\Support\Facades\Log;
use App\CentralLogics\CustomerLogic;
use App\Http\Controllers\Controller;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Models\OrderDeliveryAuditLog;
use App\Services\NotificationService;
use App\Services\LoyaltyPointsService;
use App\Models\VendorWalletTransaction;
use App\Http\Traits\VendorEmployeeAccess;
use App\Jobs\SendTelegramNotificationJob;
use App\Http\Resources\Admin\OrderResource;

use App\Http\Resources\Admin\ReceiptResource;
use App\Http\Resources\Admin\OrderShowResource;
use App\Http\Resources\Vendor\OrderReceiverResource;
use App\Http\Requests\Admin\Order\UpdateOrderRequest;
use App\Http\Requests\Api\v3\Vendor\OrderStoreReqeust;
use App\Http\Resources\Vendor\VendorOrderShowResource;


class OrderController extends Controller
{
    use VendorEmployeeAccess;

    public OrderService $orderService;
    public RestaurantService $restaurantService;
    public NotificationService $notificationService;
    protected FinanceService $financeService;
    protected LoyaltyPointsService $loyaltyPointsService;

    public function __construct(FinanceService $financeService, RestaurantService $restaurantService, NotificationService $notificationService, OrderService $orderService, LoyaltyPointsService $loyaltyPointsService)
    {
        $this->financeService = $financeService;
        $this->notificationService = $notificationService;
        $this->orderService = $orderService;
        $this->restaurantService = $restaurantService;
        $this->loyaltyPointsService = $loyaltyPointsService;
    }

    public function counts(Request $request)
    {
        $vendor = $this->getActingVendor();
        $store = $vendor->store;
        // $warehouse_ids = $vendor->warehouses->pluck('id');
        $counts = [
            'all' => Order::where('store_id', $store->id)
                ->whereIn('order_status', ['accepted', 'picked_up', 'delivered', 'canceled', 'refunded', 'postponed', 'successful', 'installment'])
                ->count(),
            // 'pending' => Order::where('store_id', $store->id)->where('order_status', 'pending')->count(),
            // 'confirmed' => Order::where('store_id', $store->id)->where('order_status', 'confirmed')->count(),
            'accepted' => Order::where('store_id', $store->id)->where('order_status', 'accepted')->count(),
            // 'processing' => Order::where('store_id', $store->id)->where('order_status', 'processing')->count(),
            // 'handover' => Order::where('store_id', $store->id)->where('order_status', 'handover')->count(),
            'picked_up' => Order::where('store_id', $store->id)->where('order_status', 'picked_up')->count(),
            'delivered' => Order::where('store_id', $store->id)->where('order_status', 'delivered')->count(),
            'canceled' => Order::where('store_id', $store->id)->where('order_status', 'canceled')->count(),
            // 'failed' => Order::where('store_id', $store->id)->where('order_status', 'failed')->count(),
            'refunded' => Order::where('store_id', $store->id)->where('order_status', 'refunded')->count(),
            'postponed' => Order::where('store_id', $store->id)->where('order_status', 'postponed')->count(),
            'successful' => Order::where('store_id', $store->id)->where('order_status', 'successful')->count(),
            'installment' => Order::where('store_id', $store->id)->where('order_status', 'installment')->count()

        ];

        return response()->json($counts);;
    }

    public function countTotalPrice(Request $request)
    {
        $vendor = $this->getActingVendor();
        $store = $vendor->store;


        $query = $this->buildFilterQuery($request, $store->id);


        $totalOrders = $query->count();
        $totalPrice = $query->sum('order_amount');


        $debug = [];
        if ($request->has('debug')) {
            $debug = [
                'total_orders_found' => $totalOrders,
                'has_installment_param' => $request->has_installment ?? 'not_set',
                'orders_with_installments' => Order::where('store_id', $store->id)
                    ->whereHas('orderInstallment', function ($q) {
                        return $q->where('remaining_balance', '>', 0);
                    })->count(),
                'orders_without_installments' => Order::where('store_id', $store->id)
                    ->whereDoesntHave('orderInstallment')->count(),
            ];
        }

        return response()->json([
            'total_price' => $totalPrice,
            'total_orders' => $totalOrders,
            ...$debug
        ]);
    }

    /**
     * Build filtered query for orders (shared between list and countTotalPrice)
     */
    private function buildFilterQuery(Request $request, $storeId)
    {
        // Update checked orders
        Order::where(['checked' => 0])->update(['checked' => 1]);

        $query = Order::with(['customer', 'store', 'warehouse'])
            ->where('store_id', $storeId)->whereIn('order_status', OrderStatusEnum::cases())
            ->when($request->has('store_ids'), function ($query) use ($request) {
                return $query->whereIn('store_id', $request->store_ids);
            })
            ->when($request->has('has_installment'), function ($query) use ($request) {
                if ($request->has_installment === 'true' || $request->has_installment === true) {
                    // Only orders WITH installments (remaining balance > 0)
                    return $query->whereHas('orderInstallment', function ($q) {
                        return $q->where('remaining_balance', '>', 0);
                    });
                } else {
                    // Only orders WITHOUT installments (no installment record OR remaining balance = 0)
                    return $query->where(function ($q) {
                        $q->whereDoesntHave('orderInstallment')
                          ->orWhereHas('orderInstallment', function ($subQ) {
                              return $subQ->where('remaining_balance', '<=', 0);
                          });
                    });
                }
            })
            ->when($request->has('status') && !empty($request->status), function ($query) use ($request) {
                return $query->whereIn('order_status', $request->status);
            })
            ->when($request->has('scheduled') && $request->status == 'all', function ($query) {
                return $query->scheduled();
            })
            ->when(
                $request->has('from_date') && $request->from_date != null,
                function ($query) use ($request) {
                    $endDate = $request->has('to_date') && $request->to_date != null
                        ? $request->to_date . " 23:59:59"
                        : now() . " 23:59:59";
                    return $query->whereBetween(
                        'created_at',
                        [$request->from_date . " 00:00:00", $endDate]
                    );
                }
            )
            ->when($request->has('search'), function ($query) use ($request) {
                return $query->whereHas('customer', function ($query) use ($request) {
                    return $query->where('f_name', 'like', '%' . $request->search . '%')
                        ->orWhere('l_name', 'like', '%' . $request->search . '%')
                        ->orWhere('phone', 'like', '%' . $request->search . '%');
                })->orWhere('id', 'like', $request->search . '%');
            })
            ->Notpos();

        return $query;
    }

    public function list(Request $request)
    {

        $orders = $this->filterList($request)->paginate($request->per_page ?? 12);

        // Eager-load wallet transactions and related wallets for this page
        $orders->getCollection()->load(['walletTransactions.vendorWallet.wallet']);

        $orders->getCollection()->transform(function ($order) {
            $order->products = collect($order->details)->map(function ($item) {
                return (new \App\Http\Resources\Admin\OrderShowProductResource((object) $item))->toArray(request());
            });

            $logos = [];
            foreach ($order->walletTransactions as $tx) {
                if (isset($tx->status) && !in_array($tx->status, ['success', 'pending'])) {
                    continue;
                }
                $logoPath = optional($tx->vendorWallet)->logo ?: optional(optional($tx->vendorWallet)->wallet)->logo;
                if (!$logoPath) {
                    $logoPath = 'assets/wallet_icons/wallet.png';
                }
                $logos[] = ltrim($logoPath, '/');
            }
            $logos = array_values(array_unique($logos));
            $order->wallets = array_map(fn($u) => ['logo' => $u], $logos);

            return $order;
        });
        return \App\Http\Resources\Vendor\OrderResource::collection($orders);
    }

    public function details(Order $order)
    {
        // Load all relationships needed for editing
        $order->load([
            'customer',
            'store',
            'warehouse',
            'delivery_man',
            'details.product',
            'orderInstallment',
            'walletTransactions.vendorWallet.wallet',
            'transaction'
        ]);

        return VendorOrderShowResource::make($order);
    }

    public function status(Request $request)
    {

        $order = Order::Notpos()->find($request->id);

        if (!$order) {
            return response()->json(['message' => trans('messages.order_not_found')], 404);
        }

        if (in_array($order->order_status, ['delivered', 'refunded', 'failed'])) {
            if (!($order->order_status === 'successful' && $request->order_status === 'refunded' && $request->has('refund_reason'))) {
                return response()->json(['message' => trans('messages.you_can_not_change_the_status_of_a_completed_order')], 400);
            }
        }

        if ($order['delivery_man_id'] == null && $request->order_status == 'out_for_delivery') {
            return response()->json(['message' => trans('messages.please_assign_deliveryman_first')], 400);
        }

        // if (
        //     $request->order_status == 'delivered' && $order['transaction_reference'] == null && !in_array(
        //         $order['payment_method'],
        //         ['cash_on_delivery', 'wallet']
        //     )
        // ) {
        //     return response()->json(['message' => trans('messages.add_your_payment_ref_first')], 400);
        // }

        if ($request->order_status == 'delivered') {
            // if ($order->transaction == null) {
            //     if ($order->payment_method == "cash_on_delivery") {
            //         if ($order->order_type == 'take_away') {
            //             $ol = OrderLogic::create_transaction($order, 'restaurant', null);
            //         } else {
            //             if ($order->delivery_man_id) {
            //                 $ol = OrderLogic::create_transaction($order, 'deliveryman', null);
            //             } else {
            //                 if ($order->user_id) {
            //                     $ol = OrderLogic::create_transaction($order, false, null);
            //                 }
            //             }
            //         }
            //     } else {
            //         $ol = OrderLogic::create_transaction($order, 'admin', null);
            //     }
            //     if (!$ol) {
            //         return response()->json(['message' => trans('messages.failed_to_create_order_transaction')], 500);
            //     }
            // } else {
            //     if ($order->delivery_man_id) {
            //         $order->transaction->update(['delivery_man_id' => $order->delivery_man_id]);
            //     }
            // }

            $order->payment_status = 'paid';
            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->increment('order_count');
                $dm->current_orders = max(0, $dm->current_orders - 1);
                $dm->save();
            }
            $order->details->each(function ($item) {
                if ($item->food) {
                    $item->food->increment('order_count');
                }
            });
            if ($order->customer) { $order->customer->increment('order_count'); }
            $order->store->increment('order_count');

            // Send Firebase notification for delivered status
            $this->notificationService->sendFirebaseOrderStatusNotification('order_delivered_message', $order);
        }
        if ($request->order_status === 'successful') {

            $this->handleInventoryDeduction($order);


            if ($order->user_id) { $this->loyaltyPointsService->awardPointsForOrder($order); }


            try {
                $vendorId = $order->store?->vendor_id;
                if ($vendorId) {

                    $pendingIds = VendorWalletTransaction::where('vendor_id', $vendorId)
                        ->where('order_id', $order->id)
                        ->where(function ($q) {
                            $q->whereNull('status')->orWhereIn('status', ['pending', 'initiated']);
                        })
                        ->pluck('id');

                    if ($pendingIds->count() > 0) {
                        VendorWalletTransaction::whereIn('id', $pendingIds)->update([
                            'status' => 'success',
                            'paid_at' => now(),
                        ]);
                    } else {

                        $payable = round(($order->order_amount ?? 0) - ($order->points_used ?? 0), 2);
                        if ($payable > 0) {
                            $splits = $request->input('wallets', []);

                            $ensureVendorWalletByName = function (string $name) use ($vendorId) {
                                $wallet = Wallet::firstOrCreate(['name' => $name], ['is_available' => true]);
                                $vendorWallet = VendorWallet::firstOrCreate(
                                    ['vendor_id' => $vendorId, 'wallet_id' => $wallet->id],
                                    ['is_enabled' => true]
                                );
                                if (!$vendorWallet->is_enabled) { $vendorWallet->is_enabled = true; $vendorWallet->save(); }
                                return $vendorWallet;
                            };

                            $resolved = [];
                            $sum = 0.0;
                            if (empty($splits)) {
                                $vw = $ensureVendorWalletByName('Наличные');
                                $resolved[] = ['vendor_wallet_id' => $vw->id, 'amount' => $payable];
                            } else {
                                foreach ($splits as $entry) {
                                    $amount = isset($entry['amount']) ? (float) $entry['amount'] : 0.0;
                                    if ($amount <= 0) { continue; }

                                    $vw = null;
                                    if (!empty($entry['vendor_wallet_id'])) {
                                        $vw = VendorWallet::where('id', (int) $entry['vendor_wallet_id'])
                                            ->where('vendor_id', $vendorId)
                                            ->first();
                                    } elseif (!empty($entry['id'])) {

                                        $vw = VendorWallet::where('vendor_id', $vendorId)
                                            ->where('wallet_id', (int) $entry['id'])->first();
                                    } elseif (!empty($entry['wallet_id'])) {
                                        $vw = VendorWallet::where('vendor_id', $vendorId)
                                            ->where('wallet_id', (int) $entry['wallet_id'])->first();
                                    } elseif (!empty($entry['name'])) {
                                        $vw = $ensureVendorWalletByName((string) $entry['name']);
                                    }
                                    if (!$vw) { $vw = $ensureVendorWalletByName('Наличные'); }
                                    if (!$vw->is_enabled) { $vw->is_enabled = true; $vw->save(); }

                                    $resolved[] = ['vendor_wallet_id' => $vw->id, 'amount' => round($amount, 2)];
                                    $sum += (float) $amount;
                                }

                                if (abs(round($sum - $payable, 2)) > 0.01) {
                                    $resolved = [];
                                    $vw = $ensureVendorWalletByName('Наличные');
                                    $resolved[] = ['vendor_wallet_id' => $vw->id, 'amount' => $payable];
                                }
                            }

                            foreach ($resolved as $split) {
                                VendorWalletTransaction::create([
                                    'vendor_id' => $vendorId,
                                    'vendor_wallet_id' => $split['vendor_wallet_id'],
                                    'order_id' => $order->id,
                                    'amount' => $split['amount'],
                                    'status' => 'success',
                                    'paid_at' => now(),
                                    'meta' => ['source' => 'order_successful']
                                ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {

                \Log::warning('Wallet split recording (on success) failed: ' . $e->getMessage(), ['order_id' => $order->id]);
            }

            $order->payment_status = 'paid';
            $this->notificationService->sendFirebaseOrderStatusNotification('order_success_message', $order);
        }

        if ($request->order_status === 'canceled') {
            // Return inventory to warehouse if it was previously deducted
            $this->handleInventoryReturn($order);
        }

        if ($request->order_status === 'refunded') {
            // Feature Off 🟥 needs to be on
            // if ($order->payment_method == "cash_on_delivery" || $order->payment_status == "unpaid") {
            //     return response()->json(['message' => trans('messages.you_can_not_refund_a_cod_order')], 400);
            // }

            if (!$request->has('refund_reason')) {
                return response()->json(['message' => 'Refund reason is required'], 422);
            }

            $rt = OrderLogic::refund_order($order);

            // Feature Off 🟥 needs to be on
            // if (!$rt) {
            //     return response()->json(['message' => trans('messages.failed_to_create_order_transaction')], 500);
            // }

            // Delete installment if exists - if order is refunded, they don't owe money anymore
            if ($order->orderInstallment) {
                $order->orderInstallment->delete();
            }

            // Return inventory to warehouse if it was previously deducted
            $this->handleInventoryReturn($order);

            if (
                $order->payment_status == "paid" &&
                BusinessSetting::where('key', 'wallet_add_refund')->first()->value == 1
            ) {
                CustomerLogic::create_wallet_transaction(
                    $order->user_id,
                    $order->order_amount,
                    'order_refund',
                    $order->id
                );
            }

            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = max(0, $dm->current_orders - 1);
                $dm->save();
            }

            $this->notificationService->sendFirebaseOrderStatusNotification('order_refunded_message', $order);
        }

        if ($request->order_status === 'canceled') {
            if (in_array($order->order_status, ['delivered', 'canceled', 'refund_requested', 'refunded', 'failed'])) {
                return response()->json(['message' => trans('messages.you_can_not_cancel_a_completed_order')], 400);
            }

            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = max(0, $dm->current_orders - 1);
                $dm->save();
            }

            $this->notificationService->sendFirebaseOrderStatusNotification('order_canceled_message', $order);
        }

        // else {
        //     if ($request->order_status == 'refunded') {
        //         if ($order->payment_method == "cash_on_delivery" || $order->payment_status == "unpaid") {
        //             return response()->json(['message' => trans('messages.you_can_not_refund_a_cod_order')], 400);
        //         }
        //         if (isset($order->delivered)) {
        //             $rt = OrderLogic::refund_order($order);

        //             if (!$rt) {
        //                 return response()->json(['message' => trans('messages.failed_to_create_order_transaction')], 500);
        //             }
        //         }

        //         if (
        //             $order->payment_status == "paid" && BusinessSetting::where(
        //                 'key',
        //                 'wallet_add_refund'
        //             )->first()->value == 1
        //         ) {
        //             CustomerLogic::create_wallet_transaction(
        //                 $order->user_id,
        //                 $order->order_amount,
        //                 'order_refund',
        //                 $order->id
        //             );
        //         }

        //         if ($order->delivery_man) {
        //             $dm = $order->delivery_man;
        //             $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
        //             $dm->save();
        //         }

        //         // Send Firebase notification for refunded status
        //         $this->notificationService->sendFirebaseOrderStatusNotification('order_refunded_message', $order);
        //     } else {
        //         if ($request->order_status == 'canceled') {
        //             if (
        //                 in_array(
        //                     $order->order_status,
        //                     ['delivered', 'canceled', 'refund_requested', 'refunded', 'failed']
        //                 )
        //             ) {
        //                 return response()->json(['message' => trans('messages.you_can_not_cancel_a_completed_order')], 400);
        //             }
        //             if ($order->delivery_man) {
        //                 $dm = $order->delivery_man;
        //                 $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
        //                 $dm->save();
        //             }

        //             // Send Firebase notification for canceled status
        //             $this->notificationService->sendFirebaseOrderStatusNotification('order_canceled_message', $order);
        //         }
        //     }
        // }
       if ($request->order_status === "picked_up" && $order->delivery_man?->telegram_user_id) {
           SendTelegramNotificationJob::dispatch($order);
        }
        $order->order_status = $request->order_status;
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'order_status' => $request->order_status,
            'vendor_id' => $this->getActingVendor()->id,
            'comment' => $request->get('refund_reason', null),
        ]);
        if ($request->order_status == 'confirmed') {
            $this->notificationService->sendFirebaseOrderStatusNotification('order_confirmation_msg', $order);
        }
        // if ($request->order_status == 'processing') {
        //     $order->processing_time = isset($request->processing_time) ? $request->processing_time : explode(
        //         '-',
        //         $order['restaurant']['delivery_time']
        //     )[0];
        //     // Send Firebase notification for processing status
        //     $this->notificationService->sendFirebaseOrderStatusNotification('order_processing_message', $order);
        // }
        $order[$request->order_status] = now();


        // DB::table('orders')
        //   ->where('id', $order->id)
        //   ->update([
        //       'order_status'            => $request->order_status,
        //       $request->order_status    => now(),   // метка времени по колонке-статусу
        //       'updated_at'              => now(),
        //   ]);

        $order->update([
            'order_status' => $request->order_status,
            $request->order_status => now(),  // метка времени по колонке-статусу
            'updated_at' => now(),
        ]);

        // $order->save();

        // if (!Helpers::send_order_notification($order)) {
        //     return response()->json(['message' => trans('messages.push_notification_faild')], 500);
        // }

        return response()->json(['message' => 'Статус заказа обновлен'], 200);
    }


    public function add_delivery_man($order_id, $delivery_man_id)
    {

        if ($delivery_man_id == 0) {
            return response()->json(
                ['message' => trans('messages.deliveryman') . ' ' . trans('messages.not_found')],
                404
            );
        }

        $order = Order::Notpos()->find($order_id);

        $deliveryman = DeliveryMan::where('id', $delivery_man_id)->where('status', 1)->first();
        if (!$order || !$deliveryman) {
            return response()->json(['message' => "Заказ или Доставщик не найдены"], 404);
        }

        if ($order->delivery_man_id == $delivery_man_id) {
            return response()->json(['message' => trans('messages.order_already_assign_to_this_deliveryman')], 400);
        }


        $cash_in_hand = $deliveryman->wallet->collected_cash ?? 0;
        $dm_max_cash = BusinessSetting::where('key', 'dm_max_cash_in_hand')->value('value') ?? 0;

        if ($order->payment_method == "cash_on_delivery" && ($cash_in_hand + $order->order_amount) >= $dm_max_cash) {
            return response()->json(['message' => trans('delivery man max cash in hand exceeds')], 400);
        }

        // Handle unassignment of the previous delivery man
        if ($order->delivery_man_id) {
            $previousDeliveryMan = $order->delivery_man;
            $previousDeliveryMan->current_orders = max(0, $previousDeliveryMan->current_orders - 1);
            $previousDeliveryMan->save();

            $unassignData = [
                'title' => trans('messages.order_push_title'),
                'description' => trans('messages.you_are_unassigned_from_a_order'),
                'order_id' => '',
                'image' => '',
                'type' => 'assign',
            ];
            //            $this->notificationService->send_firebase_notification_to_device($previousDeliveryMan->fcm_token, $unassignData);

            //            DB::table('user_notifications')->insert([
            //                'data' => json_encode($unassignData),
            //                'delivery_man_id' => $previousDeliveryMan->id,
            //                'created_at' => now(),
            //                'updated_at' => now(),
            //            ]);
        }

        // Assign the new delivery man
        $order->delivery_man_id = $delivery_man_id;
        $order->order_status = in_array($order->order_status, ['pending', 'confirmed', 'refunded']) ? 'accepted' : $order->order_status;
        $order->accepted = now();
        $order->save();

        $deliveryman->current_orders += 1;
        $deliveryman->increment('assigned_order_count');
        $deliveryman->save();

        // Send notifications to the new delivery man
        $assignData = [
            'title' => trans('messages.order_push_title'),
            'description' => trans('messages.you_are_assigned_to_a_order'),
            'order_id' => $order->id,
            'image' => '',
            'type' => 'assign',
        ];
        //        $this->notificationService->send_firebase_notification_to_device($deliveryman->fcm_token, $assignData);

        DB::table('user_notifications')->insert([
            'data' => json_encode($assignData),
            'delivery_man_id' => $deliveryman->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send notification to the customer about order status update
        //        $this->notificationService->sendFirebaseOrderStatusNotification('accepted', $order);


        return response()->json([], 200);
    }

    public function generate_invoice($id)
    {
        $order = Order::Notpos()->where('id', $id)->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        return response()->json(['invoice' => $order]);
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        $request->validate([
            'transaction_reference' => 'max:30',
        ]);

        $updated = Order::Notpos()->where('id', $id)->update([
            'transaction_reference' => $request['transaction_reference'],
        ]);

        if ($updated) {
            return response()->json(['message' => trans('messages.payment_reference_code_is_added')], 200);
        } else {
            return response()->json(['message' => 'something went wrong'], 400);
        }
    }


    public function update(UpdateOrderRequest $request, Order $order)
    {
        $validated = $request->validated();
        $customer = $order->customer;

        try {
            DB::beginTransaction();

            // Step 1: Store original order state for rollback calculations
            $originalOrder = $order->replicate();
            $originalDetails = $order->details->map(function($detail) {
                return $detail->replicate();
            });
            $originalStockDeducted = $order->stock_deducted;
            $originalPointsUsed = $order->points_used ?? 0;

            // Step 2: Restore inventory from original order if it was deducted
            if ($originalStockDeducted) {
                $this->handleInventoryReturn($order);
            }

            // Step 3: Restore loyalty points if they were used
            if ($originalPointsUsed > 0 && $order->user_id) {
                $this->loyaltyPointsService->restorePointsFromOrder($order, $originalPointsUsed);
            }

            // Step 4: Clear existing wallet transactions (mark as cancelled)
            $this->clearExistingWalletTransactions($order);

            // Step 5: Update order with new data
            $order = $this->orderService->updateVendor($validated, $customer, $order);

            // Step 6: Handle new loyalty points
            $pointsToUse = $validated['loyalty_points_used'] ?? $validated['points_to_use'] ?? 0;
            if ($pointsToUse > 0) {
                if (!$order->user_id) {
                    DB::rollBack();
                    return response()->json(['message' => 'Customer is required to use loyalty points'], 422);
                }
                $this->loyaltyPointsService->usePointsForOrder($order, $pointsToUse);
            }

            // Step 7: Handle new inventory deduction
            $this->handleInventoryDeduction($order);

            // Step 8: Handle installment validation
            if (isset($validated['is_installment']) && $validated['is_installment']) {
                if (!$order->user_id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Неидентифицированный клиент не может оформить заказ в рассрочку'
                    ], 422);
                }
            }

            // Step 9: Handle new wallet transactions
            $this->handleWalletTransactionsForUpdate($request, $order, $validated);

            // Step 10: Recalculate points earned based on new order amount
            if ($order->user_id) {
                $this->loyaltyPointsService->recalculatePointsEarned($order);
            }

            // Step 11: Update installment
            $this->financeService->updateInstallment(
                $validated,
                $order->id,
                $this->getActingVendor()->id
            );

            DB::commit();
            return response()->json(['message' => 'Order updated successfully'], 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json([
                "message" => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile()
            ], 400);
        }
    }

    public
    function edit(Request $request, Order $order)
    {

        // Validate the incoming request
        $validated = $request->validate([
            'cancel' => 'nullable|boolean',
        ]);

        // Fetch the order with related data
        $order = Order::withoutGlobalScope(RestaurantScope::class)->with([
            'details',
            'restaurant' => function ($query) {
                return $query->withCount('orders');
            },
            'customer' => function ($query) {
                return $query->withCount('orders');
            },
            'delivery_man' => function ($query) {
                return $query->withCount('orders');
            },
            'details' => function ($query) {
                return $query->with([
                    'food' => function ($q) {
                        return $q->withoutGlobalScope(RestaurantScope::class);
                    },
                    'campaign' => function ($q) {
                        return $q->withoutGlobalScope(RestaurantScope::class);
                    },
                ]);
            },
        ])->where('id', $order->id)->Notpos()->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($request->input('cancel')) {
            // Handle cancellation
            return response()->json(['message' => 'Order cancelled'], 200);
        }

        $cart = collect([]);

        foreach ($order->details as $details) {
            unset($details['food_details']);
            $details['status'] = true;
            $cart->push($details);
        }

        // Respond with the cart details
        return response()->json([
            'message' => 'Order details updated',
            'cart' => $cart,
            'selected_restaurant' => $order->restaurant,
        ]);
    }

    function quick_view(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'product_id' => 'required|int|exists:food,id',
            'order_id' => 'nullable|int',
        ]);

        // Fetch the product without the global scope
        $product = Food::withoutGlobalScope(RestaurantScope::class)->findOrFail($validated['product_id']);
        $item_type = 'food';
        $order_id = $validated['order_id'];

        // Return the necessary data as JSON
        return response()->json([
            'success' => 1,
            'product' => $product,
            'order_id' => $order_id,
            'item_type' => $item_type,
        ]);
    }

    private function filterList(Request $request)
    {
        $vendor = $this->getActingVendor();
        $store = $vendor->store;

        return $this->buildFilterQuery($request, $store->id)
            ->orderBy('id', 'desc');
    }

    public function getReceiver(Order $order)
    {
        $statusHistories = $order->statusHistories()
            ->with(['admin', 'vendor', 'admin.role'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all audit logs with appropriate actor relationships
        $auditLogs = $order->deliveryAuditLogs()
            ->with([
                'product:id,name,image',
                'vendor:id,f_name,l_name,image,phone',
                'courier:id,f_name,l_name,image,phone',
                'admin:id,f_name,l_name,image,phone',
                'vendorEmployee:id,f_name,l_name,image,phone'
            ])
            ->orderByDesc('logged_at')
            ->get();

        // Group by product_id and get the latest log per product
        $latestPerProduct = $auditLogs->unique('product_id');

        // Format
        $auditLogPayload = $latestPerProduct->map(function ($log) {
            return [
                'id' => $log->id,
                'product' => [
                    'id' => $log->product->id,
                    'name' => $log->product->name,
                    'image' => $log->product->image ? url('storage/product/' . $log->product->image) : null,
                ],
                'original_quantity' => $log->original_quantity,
                'new_quantity' => $log->new_quantity,
                'action' => $log->action,
                'reason' => $log->reason,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'f_name' => $log->actor->f_name,
                    'l_name' => $log->actor->l_name,
                    'image' => $log->actor->image ? url('storage/user/' . $log->actor->image) : null,
                    'phone' => $log->actor->phone,
                ] : null,
                'actor_role' => $log->actor_role,
                'logged_at' => $log->logged_at,
            ];
        });


        return response()->json([
            'data' => OrderReceiverResource::collection($statusHistories),
            'audit_logs' => $auditLogPayload->values(),
        ]);
    }


    public function getReceipt(Order $order)
    {
        return ReceiptResource::make($order);
    }

    public function store(OrderStoreReqeust $request)
    {
        $data = $request->validated();

        DB::beginTransaction();
        try {
            $order =   $this->orderService->storeVendor($data);


            $pointsToUse = $data['loyalty_points_used'] ?? $data['points_to_use'] ?? 0;
            if ($pointsToUse > 0) {
                if (!$order->user_id) {
                    DB::rollBack();
                    return response()->json(['message' => 'Customer is required to use loyalty points'], 422);
                }
                $this->loyaltyPointsService->usePointsForOrder($order, $pointsToUse);
            }

            $guard = request()->bearerToken() && auth('vendor_api')->check() ? 'vendor' :
                    (auth('delivery_men_api')->check() ? 'courier' :
                    (auth('admin-api')->check() ? 'admin' :
                    (auth('vendor_employee_api')->check() ? 'vendor_employee' : null)));

            $actorId = match ($guard) {
                'vendor' => auth('vendor_api')->id(),
                'courier' => auth('delivery_men_api')->id(),
                'admin' => auth('admin-api')->id(),
                'vendor_employee' => auth('vendor_employee_api')->id(),
                default => null,
            };
            foreach ($order->details as $detail) {
                OrderDeliveryAuditLog::create([
                    'order_id' => $order->id,
                    'product_id' => $detail->product_id,
                    'original_quantity' => $detail->quantity,
                    'new_quantity' => $detail->quantity,
                    'action' => 'created',
                    'actor_id' => $actorId,
                    'actor_role' => $guard,
                ]);
            }
            //Рассрочка
            if (isset($data['is_installment']) && $data['is_installment']) {
                // Validate that customer is required for installment orders
                if (!$order->user_id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Неидентифицированный клиент не может оформить заказ в рассрочку'
                    ], 422);
                }

                $this->financeService->createInstallment(
                    $data,
                    $order->id,
                    $this->getActingVendor()->id
                );
            }

            // Validate provided wallet splits against payable (products total minus points)
            $wallets = $request->input('wallets', []);
            if (is_array($wallets) && !empty($wallets)) {
                $sum = 0.0;
                foreach ($wallets as $entry) {
                    $amt = isset($entry['amount']) ? (float)$entry['amount'] : 0.0;
                    if ($amt > 0) { $sum += $amt; }
                }

                $payable = round(($order->order_amount ?? 0) - ($order->points_used ?? 0), 2);
                $isInstallment = (bool)($data['is_installment'] ?? false);

                // Not allowed to exceed payable in any case
                if (($sum - $payable) > 0.01) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Сумма на кошельке превышает сумму к оплате',
                        'sum' => round($sum, 2),
                        'payable' => $payable,
                    ], 422);
                }

                // For non-installment orders, the sum must also not be less (i.e., must equal payable)
                if (!$isInstallment && (abs($sum - $payable) > 0.01)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Сумма в кошельке должна быть равна сумме к оплате для заказов без рассрочки.',
                        'sum' => round($sum, 2),
                        'payable' => $payable,
                    ], 422);
                }
            }



            try {
                $vendorId = $this->getActingVendor()->id;
                $wallets = $request->input('wallets', []);
                $isInstallment = isset($data['is_installment']) && $data['is_installment'];
                $initialPayment = $isInstallment ? ($data['initial_payment'] ?? 0) : 0;

                if (is_array($wallets) && !empty($wallets)) {
                    foreach ($wallets as $entry) {
                        $amount = isset($entry['amount']) ? (float) $entry['amount'] : 0.0;
                        if ($amount <= 0) { continue; }

                        $vw = null;
                        if (!empty($entry['vendor_wallet_id'])) {
                            $vw = VendorWallet::where('id', (int) $entry['vendor_wallet_id'])
                                ->where('vendor_id', $vendorId)
                                ->first();
                        } elseif (!empty($entry['id'])) {

                            $vw = VendorWallet::firstOrCreate(
                                ['vendor_id' => $vendorId, 'wallet_id' => (int) $entry['id']],
                                ['is_enabled' => true]
                            );
                        } elseif (!empty($entry['wallet_id'])) {
                            $vw = VendorWallet::firstOrCreate(
                                ['vendor_id' => $vendorId, 'wallet_id' => (int) $entry['wallet_id']],
                                ['is_enabled' => true]
                            );
                        }
                        if (!$vw) { continue; }

                        if ($isInstallment && $initialPayment > 0) {
                            // For installment orders, split the wallet transaction
                            $initialPortion = min($amount, $initialPayment);
                            $remainingPortion = $amount - $initialPortion;

                            // Create transaction for initial payment (immediate success)
                            if ($initialPortion > 0) {
                                // Find or create the "Реализация" category for this vendor
                                $category = TransactionCategory::firstOrCreate(
                                    ['vendor_id' => $vendorId, 'name' => 'Реализация'],
                                    ['parent_id' => 0]
                                );

                                // Create Transaction record for initial payment
                                $initialTransaction = Transaction::create([
                                    'name' => 'Первоначальный взнос по заказу #' . $order->id,
                                    'amount' => round($initialPortion, 2),
                                    'transaction_category_id' => $category->id,
                                    'description' => 'Первоначальный взнос по рассрочке заказа #' . $order->id,
                                    'type' => TransactionTypeEnum::INCOME,
                                    'vendor_id' => $vendorId,
                                    'status' => TransactionStatusEnum::SUCCESS
                                ]);

                                // Create VendorWalletTransaction linked to the Transaction
                                VendorWalletTransaction::create([
                                    'vendor_id' => $vendorId,
                                    'vendor_wallet_id' => $vw->id,
                                    'order_id' => $order->id,
                                    'transaction_id' => $initialTransaction->id,
                                    'amount' => round($initialPortion, 2),
                                    'status' => 'success',
                                    'paid_at' => now(),
                                    'meta' => [
                                        'source' => 'order_store',
                                        'payment_type' => 'initial_payment'
                                    ]
                                ]);
                                $initialPayment -= $initialPortion; // Reduce remaining initial payment
                            }

                            // Create transaction for remaining amount (pending until order success)
                            if ($remainingPortion > 0) {
                                VendorWalletTransaction::create([
                                    'vendor_id' => $vendorId,
                                    'vendor_wallet_id' => $vw->id,
                                    'order_id' => $order->id,
                                    'amount' => round($remainingPortion, 2),
                                    'status' => 'pending',
                                    'meta' => [
                                        'source' => 'order_store',
                                        'payment_type' => 'remaining_balance'
                                    ]
                                ]);
                            }
                        } else {
                            // For non-installment orders, create single pending transaction
                            VendorWalletTransaction::create([
                                'vendor_id' => $vendorId,
                                'vendor_wallet_id' => $vw->id,
                                'order_id' => $order->id,
                                'amount' => round($amount, 2),
                                'status' => 'pending',
                                'meta' => ['source' => 'order_store']
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Wallet capture at order store failed: ' . $e->getMessage(), ['order_id' => $order->id ?? null]);
            }

            DB::commit();
            return $order;
        } catch (\Exception $exception) {

            DB::rollBack();
            return response()->json([
                "message" => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile()
            ], 400);
        }
    }









    private function handleInventoryDeduction($order): void
    {
        if (!$order->stock_deducted) {
            $order->details->each(function ($item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    // Get variation_id from order detail
                    $variationId = null;
                    if ($item->variation) {
                        $variations = json_decode($item->variation, true);
                        $variationId = $variations[0] ?? null;
                    }

                    if ($variationId) {
                        // Product has variations - deduct from ProductVariation
                        $variation = ProductVariation::where('product_id', $product->id)
                            ->where('variation_id', $variationId)
                            ->first();

                        if ($variation) {
                            $variation->quantity = max(0, $variation->quantity - $item->quantity);
                            $variation->save();
                        }

                        // Update product total quantity as sum of all variations
                        $product->quantity = $product->variations()->sum('quantity');
                    } else {
                        // Product has no variation - deduct from product quantity directly
                        $product->quantity = max(0, $product->quantity - $item->quantity);
                    }

                    $product->increment('order_count');
                    $product->save();
                }
            });

            if ($order->customer) { $order->customer->increment('order_count'); }
            $order->store->increment('order_count');
            $order->stock_deducted = true;
            $order->save();
        }
    }


    private function handleInventoryReturn($order): void
    {
        if ($order->stock_deducted) {
            $order->details->each(function ($item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    // Get variation_id from order detail
                    $variationId = null;
                    if ($item->variation) {
                        $variations = json_decode($item->variation, true);
                        $variationId = $variations[0] ?? null;
                    }

                    if ($variationId) {
                        // Product has variations - return to ProductVariation
                        $variation = ProductVariation::where('product_id', $product->id)
                            ->where('variation_id', $variationId)
                            ->first();

                        if ($variation) {
                            $variation->quantity += $item->quantity;
                            $variation->save();
                        }

                        // Update product total quantity as sum of all variations
                        $product->quantity = $product->variations()->sum('quantity');
                    } else {
                        // Product has no variation - return to product quantity directly
                        $product->quantity += $item->quantity;
                    }

                    $product->save();
                }
            });

            $order->stock_deducted = false;
            $order->save();
        }
    }

    /**
     * Clear existing wallet transactions for order update
     * Mark them as failed instead of deleting to maintain audit trail
     * Note: vendor_wallet_transactions.status enum only allows: 'success', 'pending', 'failed'
     */
    private function clearExistingWalletTransactions(Order $order): void
    {
        VendorWalletTransaction::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'success'])
            ->update([
                'status' => 'failed',
                'meta' => DB::raw("JSON_SET(COALESCE(meta, '{}'), '$.superseded_reason', 'order_update', '$.superseded_at', NOW())")
            ]);
    }

    /**
     * Handle wallet transactions for order update
     * This ensures no duplicate transactions and proper financial tracking
     */
    private function handleWalletTransactionsForUpdate($request, Order $order, array $validated): void
    {
        $vendorId = $this->getActingVendor()->id;
        $wallets = $request->input('wallets', []);
        $isInstallment = isset($validated['is_installment']) && $validated['is_installment'];
        $initialPayment = $isInstallment ? ($validated['initial_payment'] ?? 0) : 0;

        if (is_array($wallets) && !empty($wallets)) {
            // Validate wallet amounts against payable
            $sum = 0.0;
            foreach ($wallets as $entry) {
                $amt = isset($entry['amount']) ? (float)$entry['amount'] : 0.0;
                if ($amt > 0) { $sum += $amt; }
            }

            $payable = round(($order->order_amount ?? 0) - ($order->points_used ?? 0), 2);

            // Not allowed to exceed payable in any case
            if (($sum - $payable) > 0.01) {
                throw new \Exception("Сумма на кошельке превышает сумму к оплате. Сумма: {$sum}, К оплате: {$payable}");
            }

            // For non-installment orders, the sum must equal payable
            if (!$isInstallment && (abs($sum - $payable) > 0.01)) {
                throw new \Exception("Сумма в кошельке должна быть равна сумме к оплате для заказов без рассрочки. Сумма: {$sum}, К оплате: {$payable}");
            }

            // Create new wallet transactions
            foreach ($wallets as $entry) {
                $amount = isset($entry['amount']) ? (float) $entry['amount'] : 0.0;
                if ($amount <= 0) { continue; }

                $vw = null;
                if (!empty($entry['vendor_wallet_id'])) {
                    $vw = VendorWallet::where('id', (int) $entry['vendor_wallet_id'])
                        ->where('vendor_id', $vendorId)
                        ->first();
                } elseif (!empty($entry['id'])) {
                    $vw = VendorWallet::firstOrCreate(
                        ['vendor_id' => $vendorId, 'wallet_id' => (int) $entry['id']],
                        ['is_enabled' => true]
                    );
                } elseif (!empty($entry['wallet_id'])) {
                    $vw = VendorWallet::firstOrCreate(
                        ['vendor_id' => $vendorId, 'wallet_id' => (int) $entry['wallet_id']],
                        ['is_enabled' => true]
                    );
                }
                if (!$vw) { continue; }

                if ($isInstallment && $initialPayment > 0) {
                    // For installment orders, split the wallet transaction
                    $initialPortion = min($amount, $initialPayment);
                    $remainingPortion = $amount - $initialPortion;

                    // Create transaction for initial payment
                    if ($initialPortion > 0) {
                        VendorWalletTransaction::create([
                            'vendor_id' => $vendorId,
                            'vendor_wallet_id' => $vw->id,
                            'order_id' => $order->id,
                            'amount' => round($initialPortion, 2),
                            'status' => 'pending',
                            'meta' => [
                                'source' => 'order_update',
                                'payment_type' => 'initial_payment'
                            ]
                        ]);
                        $initialPayment -= $initialPortion;
                    }

                    // Create transaction for remaining amount
                    if ($remainingPortion > 0) {
                        VendorWalletTransaction::create([
                            'vendor_id' => $vendorId,
                            'vendor_wallet_id' => $vw->id,
                            'order_id' => $order->id,
                            'amount' => round($remainingPortion, 2),
                            'status' => 'pending',
                            'meta' => [
                                'source' => 'order_update',
                                'payment_type' => 'remaining_balance'
                            ]
                        ]);
                    }
                } else {
                    // For non-installment orders, create single pending transaction
                    VendorWalletTransaction::create([
                        'vendor_id' => $vendorId,
                        'vendor_wallet_id' => $vw->id,
                        'order_id' => $order->id,
                        'amount' => round($amount, 2),
                        'status' => 'pending',
                        'meta' => ['source' => 'order_update']
                    ]);
                }
            }
        }
    }

}
