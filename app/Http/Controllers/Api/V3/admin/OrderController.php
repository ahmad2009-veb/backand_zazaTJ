<?php

namespace App\Http\Controllers\Api\V3\admin;

use Carbon\Carbon;
use App\Models\Food;
use App\Models\Zone;
use App\Models\Order;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use Intervention\Image\Point;
use App\Enums\OrderStatusEnum;
use App\Services\OrderService;
use App\Services\StoreService;
use App\Models\BusinessSetting;
use App\Scopes\RestaurantScope;
use App\CentralLogics\OrderLogic;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use App\Services\RestaurantService;
use App\Http\Controllers\Controller;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Services\NotificationService;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\Admin\ReceiptResource;
use App\Http\Resources\Admin\OrderShowResource;
use App\Http\Resources\Admin\OrderReceiverResurce;
use App\Http\Requests\Api\v3\UpdateShippingRequest;
use App\Http\Requests\Admin\Order\StoreOrderRequest;
use App\Http\Requests\Admin\Order\UpdateOrderRequest;

class OrderController extends Controller
{
    public OrderService $orderService;
    public RestaurantService $restaurantService;
    public NotificationService $notificationService;
    public StoreService $storeService;

    public function __construct(RestaurantService $restaurantService, NotificationService $notificationService, OrderService $orderService, StoreService $storeService)
    {
        $this->notificationService = $notificationService;
        $this->orderService = $orderService;
        $this->restaurantService = $restaurantService;
        $this->storeService = $storeService;
    }

    public function store(StoreOrderRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $order = $this->orderService->storeAdmin($validated);
            DB::commit();
            return $order;
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json(["message" => $exception->getMessage(), 'line' => $exception->getLine()], 400);
        }
    }


    private function filterList(Request $request)
    {
        // Update checked orders
        Order::where(['checked' => 0])->update(['checked' => 1]);

        return Order::query()->notStore()->whereIn('order_status', OrderStatusEnum::cases())->with(['customer'])

            ->when($request->has('status') && !empty($request->status), function ($query) use ($request) {
                return $query->whereIn('order_status', $request->status);
            })
            ->when($request->has('scheduled') && $request->status == 'all', function ($query) {
                return $query->scheduled();
            })
            ->when(
                $request->has('from_date') && $request->from_date != null,
                function ($query) use ($request) {
                    return $query->whereBetween(
                        'created_at',
                        [$request->from_date . " 00:00:00", now() . " 23:59:59"]
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
            ->when($request->has('warehouse_id') && $request->warehouse_id != 9999, function ($query) use ($request) {
                $query->where('warehouse_id', $request->warehouse_id);
            })
            ->when($request->has('warehouse_id') && $request->warehouse_id == 9999, function ($query) use ($request) {
                $query->whereNull('warehouse_id')->whereNull('store_id');
            })
            ->Notpos()

            ->orderBy('schedule_at', 'desc');
    }

    public function counts()
    {
        $counts = [
            'all' => Order::query()->notStore()->whereIn('order_status', ['accepted', 'picked_up', 'delivered', 'successful', 'postponed', 'canceled', 'refunded'])->count(),
            // 'pending' => Order::notStore()->where('order_status', 'pending')->count(),
            // 'confirmed' => Order::notStore()->where('order_status', 'confirmed')->count(),
            'accepted' => Order::notStore()->where('order_status', 'accepted')->count(),
            // 'processing' => Order::notStore()->where('order_status', 'processing')->count(),
            // 'handover' => Order::notStore()->where('order_status', 'handover')->count(),
            'picked_up' => Order::notStore()->where('order_status', 'picked_up')->count(),
            'delivered' => Order::notStore()->where('order_status', 'delivered')->count(),
            'canceled' => Order::notStore()->where('order_status', 'canceled')->count(),
            'successful' => Order::notStore()->where('order_status', 'successful')->count(),
            'postponed' => Order::notStore()->where('order_status', 'postponed')->count(),


            // 'failed' => Order::where('order_status', 'failed')->count(),
            'refunded' => Order::notStore()->where('order_status', 'refunded')->count(),
        ];

        return response()->json($counts);
    }

    public function list(Request $request)
    {

        $orders = $this->filterList($request)->paginate($request->per_page ?? 12);

        return OrderResource::collection($orders);
    }

    public function details(Order $order)
    {

        return OrderShowResource::make($order);
    }

    public function status(Request $request)
    {
        $order = Order::Notpos()->find($request->id);
        if (!$order) {
            return response()->json(['message' => trans('messages.order_not_found')], 404);
        }

        if (in_array($order->order_status, ['delivered', 'refunded', 'failed'])) {
            return response()->json(['message' => trans('messages.you_can_not_change_the_status_of_a_completed_order')], 400);
        }

        if ($order['delivery_man_id'] == null && $request->order_status == 'out_for_delivery') {
            return response()->json(['message' => trans('messages.please_assign_deliveryman_first')], 400);
        }


        if ($request->order_status == 'delivered') {


            $order->payment_status = 'paid';
            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->increment('order_count');
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();
            }
            $order->details->each(function ($item, $key) {
                if ($item->product) {
                    $item->product->increment('order_count');
                }
            });
            $order->customer->increment('order_count');
            $order->store?->increment('order_count');

            // Send Firebase notification for delivered status
            //            $this->notificationService->sendFirebaseOrderStatusNotification('order_delivered_message', $order);
        } else {
            if ($request->order_status == 'refunded') {
                if ($order->payment_method == "cash_on_delivery" || $order->payment_status == "unpaid") {
                    return response()->json(['message' => trans('messages.you_can_not_refund_a_cod_order')], 400);
                }


                if ($order->delivery_man) {
                    $dm = $order->delivery_man;
                    $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                    $dm->save();
                }

                // Send Firebase notification for refunded status
                //                $this->notificationService->sendFirebaseOrderStatusNotification('order_refunded_message', $order);
            } else {
                if ($request->order_status == 'canceled') {
                    if (
                        in_array(
                            $order->order_status,
                            ['delivered', 'canceled', 'refund_requested', 'refunded', 'failed']
                        )
                    ) {
                        return response()->json(['message' => trans('messages.you_can_not_cancel_a_completed_order')], 400);
                    }
                    if ($order->delivery_man) {
                        $dm = $order->delivery_man;
                        $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                        $dm->save();
                    }

                    // Send Firebase notification for canceled status
                    //                    $this->notificationService->sendFirebaseOrderStatusNotification('order_canceled_message', $order);
                }
            }
        }
        $order->order_status = $request->order_status;
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'order_status' => $request->order_status,
            'admin_id' => auth('admin-api')->user()->id,
        ]);
        if ($request->order_status == 'confirmed') {
            //            $this->notificationService->sendFirebaseOrderStatusNotification('order_confirmation_msg', $order);
        }
        if ($request->order_status == 'processing') {
            $order->processing_time = isset($request->processing_time) ? $request->processing_time : explode(
                '-',
                $order['restaurant']['delivery_time']
            )[0];
            // Send Firebase notification for processing status
            //            $this->notificationService->sendFirebaseOrderStatusNotification('order_processing_message', $order);
        }
        $order[$request->order_status] = now();
        $order->save();

        //        if (!Helpers::send_order_notification($order)) {
        //            return response()->json(['message' => trans('messages.push_notification_failed')], 500);
        //        }

        return response()->json(['message' => trans('messages.order_status_updated')], 200);
    }

    public function update_shipping(UpdateShippingRequest $request, Order $order)
    {

        if ($request->latitude && $request->longitude) {
            $point = new Point($request->latitude, $request->longitude);
            $zone = Zone::whereRaw('ST_Contains(coordinates, Point(?, ?))', [$request->latitude, $request->longitude])
                ->where('id', $order->restaurant->zone_id)
                ->first();

            if (!$zone) {
                return response()->json(['message' => trans('messages.out_of_coverage')], 400);
            }
        }

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'floor' => $request->floor,
            'house' => $request->house,
            'road' => $request->road,
        ];


        $order->delivery_address = json_encode($address);
        $order->save();

        return response()->json(['message' => trans('messages.delivery_address_updated'), 'order' => $order], 200);
    }

    public function orders_export(Request $request, $type)
    {
        $orders = $this->filterList($request)->get();
        if ($type == 'excel') {
            return (new FastExcel(OrderLogic::format_export_data($orders)))->download('Orders.xlsx');
        } else {
            if ($type == 'csv') {
                return (new FastExcel(OrderLogic::format_export_data($orders)))->download('Orders.csv');
            }
        }
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
        $order->order_status = in_array($order->order_status, ['pending', 'confirmed']) ? 'accepted' : $order->order_status;
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


    public
    function generate_invoice($id)
    {
        $order = Order::Notpos()->where('id', $id)->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        return response()->json(['invoice' => $order]);
    }

    public
    function add_payment_ref_code(Request $request, $id)
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

    //    public function restaurant_filter()
    //    {
    //
    //    }

    public function search(Request $request)
    {
        $request->validate([
            'search' => 'required|string'
        ]);

        $keywords = explode(' ', $request['search']);

        $orders = Order::where(function ($query) use ($keywords) {
            foreach ($keywords as $keyword) {
                $query->orWhere('id', 'like', "%{$keyword}%")
                    ->orWhere('order_status', 'like', "%{$keyword}%")
                    ->orWhere('transaction_reference', 'like', "%{$keyword}%");
            }
        })
            ->Notpos()
            ->limit(50)
            ->get();

        $transformedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_status' => $order->order_status,
                'transaction_reference' => $order->transaction_reference,
                // Add additional fields as necessary
            ];
        });

        return response()->json([
            'orders' => $transformedOrders
        ]);
    }

    public function restaurant_order_search(Request $request)
    {

        $key = explode(' ', $request['search']);
        $orders = Order::where(['restaurant_id' => $request->restaurant_id])
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%");
                }
            })
            ->whereHas('customer', function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%");
                }
            })->get();


        return response()->json([
            'orders' => $orders
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {

        $validated = $request->validated();
        $customer = $order->customer;

        try {
            $order = $this->orderService->updateAdmin($validated, $customer, $order);
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json(["message" => $exception->getMessage(), 'line' => $exception->getLine()], 400);
        }
    }


    public
    function edit(Request $request, Order $order)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'cancle' => 'nullable|boolean',
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

        if ($request->input('cancle')) {
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

    public
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

    public
    function dispatch_list(Request $request)
    {
        $status = $request->input('status', []);


        Order::where(['checked' => 0])->update(['checked' => 1]);

        $orders = Order::with(['customer', 'restaurant'])
            ->when(isset($request->zone), function ($query) use ($request) {
                return $query->whereHas('restaurant', function ($query) use ($request) {
                    return $query->whereIn('zone_id', $request->zone);
                });
            })
            ->when($status == 'searching_for_deliverymen', function ($query) {
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'on_going', function ($query) {
                return $query->Ongoing();
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('restaurant', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(
                isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null,
                function ($query) use ($request) {
                    return $query->whereBetween(
                        'created_at',
                        [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]
                    );
                }
            )
            ->Notpos()
            ->OrderScheduledIn(30)
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));

        $orderStatus = isset($request->orderStatus) ? $request->orderStatus : [];
        $scheduled = isset($request->scheduled) ? $request->scheduled : 0;
        $vendor_ids = isset($request->vendor) ? $request->vendor : [];
        $zone_ids = isset($request->zone) ? $request->zone : [];
        $from_date = isset($request->from_date) ? $request->from_date : null;
        $to_date = isset($request->to_date) ? $request->to_date : null;
        $total = $orders->total();

        return response()->json([
            'orders' => $orders,
            'status' => $status,
            'orderStatus' => $orderStatus,
            'scheduled' => $scheduled,
            'vendor_ids' => $vendor_ids,
            'zone_ids' => $zone_ids,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'total' => $total
        ]);
    }

    public function getReceiver(Order $order)
    {

        $receiver = $order->statusHistories()->orderBy('created_at', 'desc')
            ->with('admin')->get();


        if (!$receiver) {
            return response()->json(['data' => $order->order_status]);
        }
        return response()->json(['data' => OrderReceiverResurce::collection($receiver)]);
    }

    public function export_statistics(Request $request)
    {

        $request->validate([
            'duration' => 'nullable|string|in:today,week,month,all_time',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d'
        ]);
        $duration = $request->input('duration');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $data = [];

        Order::filterByDuration($duration, $fromDate, $toDate)
            ->select(['id', 'user_id', 'delivery_address', 'order_status', 'delivery_charge', 'created_at', 'total_products_price', 'source'])
            ->chunk(100, function ($orders) use (&$data) {
                $orders->each(function ($item, $key) use (&$data) {
                    // $address = $item->delivery_address ? json_decode($item->delivery_address, true) : [];

                    $data[] = [
                        '№' => $key + 1,
                        '№ заказа' => $item->id,
                        'Дата заказа' => Carbon::parse($item->created_at)->format('d.m.Y'),
                        'Имя клиента' => $item->customer->f_name ?? null,
                        'Телефон клиента' => $item->customer->phone ?? null,
                        'Цена доставки' => $item->delivery_charge,
                        'Сумма товаров' => $item->total_products_price,
                        'Долг' => (int)$item->installment,
                        'Статус' => $item->order_status->label(),
                        'Источник' => $item->source,
                        'Итого (сумма товар + цена дост.)' => $item->delivery_charge + $item->total_products_price
                    ];
                });
            });
        $date_time = now()->format('Y-m-d_H-i-s');

        return (new FastExcel($data))->download("data_{$date_time}.xlsx");
    }

    public function getReceipt(Order $order)
    {
        return ReceiptResource::make($order);
    }
}
