<?php

namespace App\CentralLogics;

use App\Mail\OrderPlaced;
use App\Models\AddOn;
use App\Models\Admin;
use App\Models\AdminWallet;
use App\Models\BusinessSetting;
use App\Models\CustomerAddress;
use App\Models\DeliveryManWallet;
use App\Models\Food;
use App\Models\Order;
use App\Models\Order as OrderModel;
use App\Models\OrderDetail;
use App\Models\OrderTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantWallet;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderLogic
{
    public static function gen_unique_id()
    {
        return rand(1000, 9999) . '-' . Str::random(5) . '-' . time();
    }

    public static function track_order($order_id)
    {
        return Helpers::order_data_formatting(Order::with([
            'details',
            'delivery_man.rating',
        ])->where(['id' => $order_id])->first(), false);
    }

    public static function place_order(
        $customer_id,
        $email,
        $customer_info,
        $cart,
        $payment_method,
        $discount,
        $coupon_code = null
    ) {
        try {
            $or = [
                'id'               => 100000 + Order::all()->count() + 1,
                'user_id'          => $customer_id,
                'order_amount'     => CartManager::cart_grand_total($cart) - $discount,
                'payment_status'   => 'unpaid',
                'order_status'     => 'pending',
                'payment_method'   => $payment_method,
                'transaction_ref'  => null,
                'discount_amount'  => $discount,
                'coupon_code'      => $coupon_code,
                'discount_type'    => $discount == 0 ? null : 'coupon_discount',
                'shipping_address' => $customer_info['address_id'],
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            $o_id = DB::table('orders')->insertGetId($or);

            foreach ($cart as $c) {
                $product = Food::where('id', $c['id'])->first();
                $or_d    = [
                    'order_id'           => $o_id,
                    'food_id'            => $c['id'],
                    'seller_id'          => $product->added_by == 'seller' ? $product->user_id : '0',
                    'product_details'    => $product,
                    'qty'                => $c['quantity'],
                    'price'              => $c['price'],
                    'tax'                => $c['tax'] * $c['quantity'],
                    'discount'           => $c['discount'] * $c['quantity'],
                    'discount_type'      => 'discount_on_product',
                    'variant'            => $c['variant'],
                    'variation'          => json_encode($c['variations']),
                    'delivery_status'    => 'pending',
                    'shipping_method_id' => $c['shipping_method_id'],
                    'payment_status'     => 'unpaid',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
                DB::table('order_details')->insert($or_d);
            }
            if (config('mail.status')) {
                Mail::to($email)->send(new \App\Mail\OrderPlaced($o_id));
            }

        } catch (\Exception $e) {

        }

        return $o_id;
    }

    public static function updated_order_calculation($order)
    {
        return true;
    }

    //here
    public static function create_transaction($order, $received_by = false, $status = null)
    {
        $comission        = !isset($order->restaurant->comission) ? \App\Models\BusinessSetting::where('key',
            'admin_commission')->first()->value : $order->restaurant->comission;
        $order_amount     = $order->order_amount - $order->delivery_charge - $order->total_tax_amount - $order->dm_tips;
        $comission_amount = $comission ? ($order_amount / 100) * $comission : 0;
        $admin_subsidy    = 0;

        $delivery_charge_comission            = BusinessSetting::where('key', 'delivery_charge_comission')->first();
        $delivery_charge_comission_percentage = $delivery_charge_comission ? $delivery_charge_comission->value : 0;
        $comission_on_delivery                = $delivery_charge_comission_percentage * ($order->original_delivery_charge / 100);
        $comission_on_actual_delivery_fee     = ($order->delivery_charge > 0) ? $comission_on_delivery : 0;

        if ($order->free_delivery_by == 'admin') {
            $admin_subsidy = $order->original_delivery_charge;
        }

        try {
            OrderTransaction::insert([
                'vendor_id'                => $order->restaurant->vendor->id,
                'delivery_man_id'          => $order->delivery_man_id,
                'order_id'                 => $order->id,
                'order_amount'             => $order->order_amount,
                'restaurant_amount'        => $order_amount + $order->total_tax_amount - $comission_amount,
                'admin_commission'         => $comission_amount - $admin_subsidy,
                //add a new column. add the comission here
                'delivery_charge'          => $order->delivery_charge - $comission_on_actual_delivery_fee,
                //minus here
                'original_delivery_charge' => $order->original_delivery_charge - $comission_on_delivery,
                //calculate the comission with this. minus here
                'tax'                      => $order->total_tax_amount,
                'received_by'              => $received_by ? $received_by : 'admin',
                'zone_id'                  => $order->zone_id,
                'status'                   => $status,
                'dm_tips'                  => $order->dm_tips,
                'created_at'               => now(),
                'updated_at'               => now(),
                'delivery_fee_comission'   => $comission_on_actual_delivery_fee,
            ]);
            $adminWallet = AdminWallet::firstOrNew(
                ['admin_id' => Admin::where('role_id', 1)->first()->id]
            );

            $vendorWallet = RestaurantWallet::firstOrNew(
                ['vendor_id' => $order->restaurant->vendor->id]
            );
            if ($order->delivery_man && !$order->restaurant->self_delivery_system) {
                $dmWallet = DeliveryManWallet::firstOrNew(
                    ['delivery_man_id' => $order->delivery_man_id]
                );

                if ($order->delivery_man->earning == 1) {
                    $dmWallet->total_earning = $dmWallet->total_earning + $order->dm_tips + $order->original_delivery_charge - $comission_on_delivery;
                } else {
                    $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $order->dm_tips + $order->original_delivery_charge - $comission_on_delivery;
                }
            }


            $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $comission_amount + $comission_on_actual_delivery_fee - $admin_subsidy;

            if ($order->restaurant->self_delivery_system) {
                $vendorWallet->total_earning = $vendorWallet->total_earning + $order->delivery_charge + $order->dm_tips;
            } else {
                $adminWallet->delivery_charge = $adminWallet->delivery_charge + $order->delivery_charge - $comission_on_actual_delivery_fee;
            }

            $vendorWallet->total_earning = $vendorWallet->total_earning + ($order_amount + $order->total_tax_amount - $comission_amount);
            try {
                DB::beginTransaction();
                if ($received_by == 'admin') {
                    $adminWallet->digital_received = $adminWallet->digital_received + $order->order_amount;
                } else {
                    if ($received_by == 'restaurant' && $order->payment_method == 'cash_on_delivery') {
                        $vendorWallet->collected_cash = $vendorWallet->collected_cash + $order->order_amount;
                    } else {
                        if ($received_by == false) {
                            $adminWallet->manual_received = $adminWallet->manual_received + $order->order_amount;
                        } else {
                            if ($received_by == 'deliveryman' && $order->delivery_man->type == 'zone_wise' && $order->payment_method == 'cash_on_delivery') {
                                if (!isset($dmWallet)) {
                                    $dmWallet = DeliveryManWallet::firstOrNew(
                                        ['delivery_man_id' => $order->delivery_man_id]
                                    );
                                }
                                $dmWallet->collected_cash = $dmWallet->collected_cash + $order->order_amount;
                            }
                        }
                    }
                }
                if (isset($dmWallet)) {
                    $dmWallet->save();
                }
                $adminWallet->save();
                $vendorWallet->save();
                DB::commit();
                if ($order->user_id) {
                    CustomerLogic::create_loyalty_point_transaction($order->user_id, $order->id, $order->order_amount,
                        'order_place');
                }

            } catch (\Exception $e) {
                DB::rollBack();
                info($e);

                return false;
            }
        } catch (\Exception $e) {
            info($e);

            return false;
        }

        return true;
    }

    public static function refund_order($order)
    {
        $order_transaction = $order->transaction;
        if ($order_transaction == null || $order->restaurant == null) {
            return false;
        }
        $received_by = $order_transaction->received_by;

        $adminWallet = AdminWallet::firstOrNew(
            ['admin_id' => Admin::where('role_id', 1)->first()->id]
        );

        $vendorWallet = RestaurantWallet::firstOrNew(
            ['vendor_id' => $order->restaurant->vendor->id]
        );


        $adminWallet->total_commission_earning = $adminWallet->total_commission_earning - $order_transaction->admin_commission;

        $vendorWallet->total_earning = $vendorWallet->total_earning - $order_transaction->restaurant_amount;

        $refund_amount = $order->order_amount;

        $status = 'refunded_with_delivery_charge';
        if ($order->order_status == 'delivered') {
            $refund_amount = $order->order_amount - $order->delivery_charge;
            $status        = 'refunded_without_delivery_charge';
        } else {
            $adminWallet->delivery_charge = $adminWallet->delivery_charge - $order_transaction->delivery_charge;
        }
        try {
            DB::beginTransaction();
            if ($received_by == 'admin') {
                if ($order->delivery_man_id && $order->payment_method != "cash_on_delivery") {
                    $adminWallet->digital_received = $adminWallet->digital_received - $refund_amount;
                } else {
                    $adminWallet->manual_received = $adminWallet->manual_received - $refund_amount;
                }

            } else {
                if ($received_by == 'restaurant') {
                    $vendorWallet->collected_cash = $vendorWallet->collected_cash - $refund_amount;
                }

                // DB::table('account_transactions')->insert([
                //     'from_type'=>'customer',
                //     'from_id'=>$order->user_id,
                //     'current_balance'=> 0,
                //     'amount'=> $refund_amount,
                //     'method'=>'CASH',
                //     'created_at' => now(),
                //     'updated_at' => now()
                // ]);

                else {
                    if ($received_by == 'deliveryman') {
                        $dmWallet                 = DeliveryManWallet::firstOrNew(
                            ['delivery_man_id' => $order->delivery_man_id]
                        );
                        $dmWallet->collected_cash = $dmWallet->collected_cash - $refund_amount;
                        $dmWallet->save();
                    }
                }
            }
            $order_transaction->status = $status;
            $order_transaction->save();
            $adminWallet->save();
            $vendorWallet->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            info($e);

            return false;
        }

        return true;

    }

    public static function format_export_data($orders)
    {
        $data = [];
        foreach ($orders as $key => $order) {

            $data[] = [
                '#'                                                        => $key + 1,
                trans('messages.order')                                    => $order['id'],
                trans('messages.date')                                     => date('d M Y',
                    strtotime($order['created_at'])),
                trans('messages.customer')                                 => $order->customer ? $order->customer['f_name'] . ' ' . $order->customer['l_name'] : __('messages.invalid') . ' ' . __('messages.customer') . ' ' . __('messages.data'),
                trans('messages.Restaurant')                               => \Str::limit($order->restaurant ? $order->restaurant->name : __('messages.Restaurant deleted!'),
                    20, '...'),
                trans('messages.payment') . ' ' . trans('messages.status') => $order->payment_status == 'paid' ? __('messages.paid') : __('messages.unpaid'),
                trans('messages.total')                                    => \App\CentralLogics\Helpers::format_currency($order['order_amount']),
                trans('messages.order') . ' ' . trans('messages.status')   => trans('messages.' . $order['order_status']),
                trans('messages.order') . ' ' . trans('messages.type')     => trans('messages.' . $order['order_type']),
            ];
        }

        return $data;
    }

    public static function placeOrder(
        array $cart,
        string $order_type,
        string $payment_method,
        int $address_id,
        array $contact,
        ?string $notes = null
    ) {
        try {
            $restaurant = Restaurant::find($cart['restaurant']['id']);
            $address    = CustomerAddress::find($address_id);

            $totalAddonPrice          = 0;
            $productPrice             = 0;
            $restaurantDiscountAmount = 0;

            $orderDetails = [];
            $order        = new OrderModel();
            $order->id    = 100000 + OrderModel::count() + 1;
            if (OrderModel::find($order->id)) {
                $order->id = OrderModel::orderBy('id', 'desc')->first()->id + 1;
            }

            $order->user_id                  = $contact['id'];
            $order->order_amount             = $cart['total'];
            $order->payment_status           = 'unpaid';
            $order->order_status             = 'pending';
            $order->payment_method           = $payment_method;
            $order->transaction_reference    = Str::random(64);
            $order->order_note               = $notes;
            $order->order_type               = $order_type;
            $order->restaurant_id            = $cart['restaurant']['id'];
            $order->delivery_charge          = $order_type === OrderModel::DELIVERY ? Arr::get($cart, 'delivery.charge', 0) : 0;
            $order->original_delivery_charge = 0;
            $order->delivery_address         = json_encode(array_merge(
                $address->toArray(),
                [
                    'contact_person_name'   => Arr::get($contact, 'name'),
                    'contact_person_number' => Arr::get($contact, 'phone'),
                ],
            ), JSON_UNESCAPED_UNICODE);
            $order->schedule_at              = now();
            $order->scheduled                = 0;
            $order->otp                      = rand(1000, 9999);
            $order->zone_id                  = $restaurant->zone_id;
            $order->pending                  = now();
            $order->confirmed                = null;
            $order->created_at               = now();
            $order->updated_at               = now();

            foreach ($cart['items'] as $cartItem) {
                $product = Food::active()->find($cartItem['product']['id']);

                if ($product) {
                    $price        = $cartItem['form']['price'];
                    $product->tax = 0;
                    $product      = Helpers::product_data_formatting($product, false, false, app()->getLocale());

                    $selectedExtra = array_filter(
                        $cartItem['form']['extra'] ?? [],
                        fn(array $extra) => $extra['quantity'] > 0
                    );
                    $selectedExtra = collect($selectedExtra);

                    $addonData = Helpers::calculate_addon_price(
                        AddOn::whereIn('id', $selectedExtra->pluck('id')->all())->get(),
                        $selectedExtra->pluck('quantity')->all()
                    );

                    // Указываем стоимость товара за 1 еденицу без учета дополнений
                    $price -= $addonData['total_add_on_price'];
                    $price /= $cartItem['form']['quantity'];

                    $variant = join('-', $cartItem['form']['options'] ?? []);

                    $orderItem = [
                        'food_id'            => $product->id,
                        'item_campaign_id'   => null,
                        'food_details'       => json_encode($product),
                        'quantity'           => $cartItem['form']['quantity'],
                        'price'              => round($price, config('round_up_to_digit')),
                        'tax_amount'         => 0,
                        'discount_on_food'   => Helpers::product_discount_calculate($product, $price, $restaurant),
                        'discount_type'      => 'discount_on_product',
                        'variant'            => $variant,
                        'variation'          => json_encode($cartItem['form']['options'] ?? [], JSON_UNESCAPED_UNICODE),
                        'add_ons'            => json_encode($addonData['addons']),
                        'total_add_on_price' => round($addonData['total_add_on_price'], config('round_up_to_digit')),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];

                    $totalAddonPrice          += $orderItem['total_add_on_price'];
                    $productPrice             += $price * $orderItem['quantity'];
                    $restaurantDiscountAmount += $orderItem['discount_on_food'] * $orderItem['quantity'];
                    $orderDetails[]           = $orderItem;
                }
            }

            $restaurantDiscount = Helpers::get_restaurant_discount($restaurant);
            if (isset($restaurantDiscount)) {
                if ($productPrice + $totalAddonPrice < $restaurantDiscount['min_purchase']) {
                    $restaurantDiscountAmount = 0;
                }

                if ($restaurantDiscountAmount > $restaurantDiscount['max_discount']) {
                    $restaurantDiscountAmount = $restaurantDiscount['max_discount'];
                }
            }

            $couponDiscountAmount = Arr::get($cart, 'coupon_discount_amount', 0);
            $totalPrice           = $productPrice + $totalAddonPrice - $restaurantDiscountAmount - $couponDiscountAmount;

            $orderAmount = round($totalPrice + $order->delivery_charge, config('round_up_to_digit'));

            $order->coupon_discount_amount     = round($couponDiscountAmount, config('round_up_to_digit'));
            $order->coupon_discount_title      = '';
            $order->free_delivery_by           = $freeDeliveryBy ?? '';
            $order->restaurant_discount_amount = round($restaurantDiscountAmount, config('round_up_to_digit'));
            $order->total_tax_amount           = 0;
            $order->order_amount               = $orderAmount;
            $order->save();

            foreach ($orderDetails as $key => $item) {
                $orderDetails[$key]['order_id'] = $order->id;
            }

            OrderDetail::insert($orderDetails);
            Helpers::send_order_notification($order);

            $customer          = User::find($contact['id']);
            $customer->zone_id = $restaurant->zone_id;
            $customer->save();

            $restaurant->increment('total_order');

            SMS_module::send(
                Arr::get($contact, 'phone'),
                "Ваш заказ под номером {$order->id} оформлен"
            );
            
            return $order;
        } catch (\Throwable $e) {
            logger()->error($e);

            throw new \Exception($e->getMessage());
        }
    }

    public static function placeOrderPos(
        array $cart,
        string $order_type,
        string $payment_method,
        int $address_id,
        array $contact,
        ?string $notes = null
    ) {
        try {
            $restaurant = Restaurant::find($cart['restaurant']['id']);
            $address    = CustomerAddress::find($address_id);

            $totalAddonPrice          = 0;
            $productPrice             = 0;
            $restaurantDiscountAmount = 0;

            $orderDetails = [];
            $order        = new OrderModel();
            $order->id    = 100000 + OrderModel::count() + 1;
            if (OrderModel::find($order->id)) {
                $order->id = OrderModel::orderBy('id', 'desc')->first()->id + 1;
            }

            $order->user_id                  = $contact['id'];
            $order->order_amount             = $cart['total'];
            $order->payment_status           = 'unpaid';
            $order->order_status             = 'confirmed';
            $order->payment_method           = $payment_method;
            $order->transaction_reference    = Str::random(64);
            $order->order_note               = $notes;
            $order->order_type               = $order_type;
            $order->restaurant_id            = $cart['restaurant']['id'];
            $order->delivery_charge          = $order_type === OrderModel::DELIVERY ? Arr::get($cart, 'delivery.charge', 0) : 0;
            $order->original_delivery_charge = 0;
            $order->delivery_address         = json_encode(array_merge(
                $address->toArray(),
                [
                    'contact_person_name'   => Arr::get($contact, 'name'),
                    'contact_person_number' => Arr::get($contact, 'phone'),
                ],
            ), JSON_UNESCAPED_UNICODE);
            $order->schedule_at              = now();
            $order->scheduled                = 0;
            $order->otp                      = rand(1000, 9999);
            $order->zone_id                  = $restaurant->zone_id;
            $order->pending                  = now();
            $order->confirmed                = now();
            $order->created_at               = now();
            $order->updated_at               = now();

            foreach ($cart['items'] as $cartItem) {
                $product = Food::active()->find($cartItem['product']['id']);

                if ($product) {
                    $price        = $cartItem['form']['price'];
                    $product->tax = 0;
                    $product      = Helpers::product_data_formatting($product, false, false, app()->getLocale());

                    $selectedExtra = array_filter(
                        $cartItem['form']['extra'] ?? [],
                        fn(array $extra) => $extra['quantity'] > 0
                    );
                    $selectedExtra = collect($selectedExtra);

                    $addonData = Helpers::calculate_addon_price(
                        AddOn::whereIn('id', $selectedExtra->pluck('id')->all())->get(),
                        $selectedExtra->pluck('quantity')->all()
                    );

                    // Указываем стоимость товара за 1 еденицу без учета дополнений
                    $price -= $addonData['total_add_on_price'];
                    $price /= $cartItem['form']['quantity'];

                    $variant = join('-', $cartItem['form']['options'] ?? []);

                    $orderItem = [
                        'food_id'            => $product->id,
                        'item_campaign_id'   => null,
                        'food_details'       => json_encode($product),
                        'quantity'           => $cartItem['form']['quantity'],
                        'price'              => round($price, config('round_up_to_digit')),
                        'tax_amount'         => 0,
                        'discount_on_food'   => Helpers::product_discount_calculate($product, $price, $restaurant),
                        'discount_type'      => 'discount_on_product',
                        'variant'            => $variant,
                        'variation'          => json_encode($cartItem['form']['options'] ?? [], JSON_UNESCAPED_UNICODE),
                        'add_ons'            => json_encode($addonData['addons']),
                        'total_add_on_price' => round($addonData['total_add_on_price'], config('round_up_to_digit')),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];

                    $totalAddonPrice          += $orderItem['total_add_on_price'];
                    $productPrice             += $price * $orderItem['quantity'];
                    $restaurantDiscountAmount += $orderItem['discount_on_food'] * $orderItem['quantity'];
                    $orderDetails[]           = $orderItem;
                }
            }

            $restaurantDiscount = Helpers::get_restaurant_discount($restaurant);
            if (isset($restaurantDiscount)) {
                if ($productPrice + $totalAddonPrice < $restaurantDiscount['min_purchase']) {
                    $restaurantDiscountAmount = 0;
                }

                if ($restaurantDiscountAmount > $restaurantDiscount['max_discount']) {
                    $restaurantDiscountAmount = $restaurantDiscount['max_discount'];
                }
            }

            $couponDiscountAmount = Arr::get($cart, 'coupon_discount_amount', 0);
            $totalPrice           = $productPrice + $totalAddonPrice - $restaurantDiscountAmount - $couponDiscountAmount;

            $orderAmount = round($totalPrice + $order->delivery_charge, config('round_up_to_digit'));

            $order->coupon_discount_amount     = round($couponDiscountAmount, config('round_up_to_digit'));
            $order->coupon_discount_title      = '';
            $order->free_delivery_by           = $freeDeliveryBy ?? '';
            $order->restaurant_discount_amount = round($restaurantDiscountAmount, config('round_up_to_digit'));
            $order->total_tax_amount           = 0;
            $order->order_amount               = $orderAmount;
            $order->save();

            foreach ($orderDetails as $key => $item) {
                $orderDetails[$key]['order_id'] = $order->id;
            }

            OrderDetail::insert($orderDetails);
            Helpers::send_order_notification($order);

            $customer          = User::find($contact['id']);
            $customer->zone_id = $restaurant->zone_id;
            $customer->save();

            $restaurant->increment('total_order');

            SMS_module::send(
                Arr::get($contact, 'phone'),
                "Ваш заказ под номером {$order->id} оформлен"
            );

            return $order;
        } catch (\Throwable $e) {
            logger()->error($e);

            throw new \Exception($e->getMessage());
        }
    }
}
