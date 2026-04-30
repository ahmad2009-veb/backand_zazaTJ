<?php

namespace App\Services;

use Exception;
use Carbon\Carbon;
use App\Models\Food;
use App\Models\User;
use App\Models\AddOn;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\OrderDetail;
use App\Models\SpentPoints;
use App\Models\LoyaltyPoint;
use App\CentralLogics\Helpers;
use App\Enums\OrderStatusEnum;
use App\Models\BusinessSetting;
use App\Models\CustomerAddress;
use App\Models\VendorEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OrderResoursce;

class OrderService
{
    public CampaignService $campaignService;

    public function __construct(CampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    /**
     * Get the acting vendor - works for both vendor and employee authentication
     * 
     * @return \App\Models\Vendor|null
     */
    protected function getActingVendor()
    {
        $vendorUser = Auth::guard('vendor_api')->user();
        $employeeUser = Auth::guard('vendor_employee_api')->user();

        // If employee is authenticated, get vendor through employee relationship
        if ($employeeUser && $employeeUser instanceof VendorEmployee) {
            return $employeeUser->vendor;
        }

        // If vendor is authenticated, return vendor directly
        if ($vendorUser && $vendorUser instanceof Vendor) {
            return $vendorUser;
        }

        return null;
    }

    public function storeAdmin($data)
    {
        $customer = User::query()->find($data['customer_id']);
        

        $address = [
                'contact_person_name' => $customer?->f_name ?? null,
                'contact_person_number' => $customer?->phone ?? null,
                'address' => null,
                'floor' => null,
                'road' => $data['delivery_address'],
                'house' => null,
                'address_type' => 'home',
                'longitude' => null,
                'latitude' => null,
                'user_id' => $customer?->id,
            ];
            $address = CustomerAddress::query()->create($address);

        $order = Order::create([
            'user_id' => $customer?->id,
            'order_amount' => isset($data['order_amount']) ?? 0,
            'store_id' => $data['store_id'] ?? null,
            'delivery_type' => $data['delivery_type'] ?? 'standard',
            'delivery_address' => json_encode($address),
            'order_note' => $data['order_note'] ?? '',
            'schedule_at' => $data['delivery_time'] ?? now(),
            'scheduled' => $data['delivery_time'] ?? 1,
            'source' => 'dashboard',
            'comment' => $data['comment'] ?? null,
            'comment_for_store' => $data['comment_for_store'] ?? null,
            'comment_for_warehouse' => $data['comment_for_warehouse'] ?? null,
            'order_status' => 'accepted',
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'delivery_man_id' => $data['delivery_man_id'] ?? null,
        ]);

        $order_history = DB::table('order_status_histories')->insert([
            'order_status' => 'create',
            'order_id' => $order->id,
            'admin_id' => auth()->user()->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $order_history = DB::table('order_status_histories')->insert([
            'order_status' => 'accepted',
            'order_id' => $order->id,
            'admin_id' => auth()->user()->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $calcOrderAmount = 0;
        $calcTotalProductPrice = 0;
        //создание order_details
        collect($data['products'])
            ->each(function ($item) use (&$calcOrderAmount, &$calcTotalProductPrice, $order) {
                $product = Product::active()->findOrFail($item['id']);

                $product->order_count++;
                $product->save();

                // Support both old 'variation' and new 'variation_id' formats
                $variationId = $item['variation_id'] ?? $item['variation'] ?? null;

                $orderDetail = OrderDetail::create([
                    'product_id' => $item['id'],
                    'order_id' => $order['id'],
                    'price' => $item['price'] ?? $product->price,
                    'quantity' => $item['quantity'],
                    'variation' => $variationId ? json_encode([$variationId]) : json_encode([]),
                    'product_details' => Helpers::product_data_formatting($product, false, false, app()->getLocale()),
                    //                    'add_ons' => $this->make_add_ons($item['add_ons'] ?? [], $item['quantity']),
                    //                    'total_add_on_price' => $this->calcAddOns($item['add_ons'] ?? [], $item['quantity']),
                ]);
                $calcOrderAmount += ($orderDetail->price * $orderDetail->quantity) + $orderDetail->total_add_on_price;
                $withDiscount = $calcOrderAmount * (1 - $orderDetail->discount/100);
                $calcTotalProductPrice = $withDiscount;
            });
        $order->total_products_price = $calcTotalProductPrice;
        // Подсчет суммы доставки
        if (isset($data['delivery_charge'])) {

            $order->order_amount = $calcOrderAmount + $data['delivery_charge'];
            $order->delivery_charge = $data['delivery_charge'];
        } else {
            $delivery_type_price = DB::table('delivery_types')->where('name', $order->delivery_type)->first()->value;
            $order->order_amount = $calcOrderAmount + $delivery_type_price;
            $order->delivery_charge = $delivery_type_price;
        }

        $order->save();

        //        if ($order->payment_method == 'bonus_discount') {
        //            try {
        //                $this->bonusDiscount($order, $customer);
        //            } catch (\Exception $exception) {
        //                throw new \Exception($exception->getMessage());
        //
        //            }
        //
        //        }

               $this->campaignService->handleCampaign($data, $order->order_amount, $order->id);


        return $order;
    }


    public function updateAdmin($data, $customer, $order)
    {
        $address = [
            'contact_person_name' => $customer?->f_name ?? null,
            'contact_person_number' => $customer?->phone ?? null,
            'address' => null,
            'floor' => null,
            'road' => $data['delivery_address'],
            'house' => null,
            'address_type' => 'Delivery',
            'longitude' => null,
            'latitude' => null,
        ];
        $order->details()->delete();

        $order->update([
            'store_id' => $data['store_id'] ?? $order->store_id,
            'delivery_type' => $data['delivery_type'] ?? 'standard',
            'delivery_address' => json_encode($address),
            'order_note' => $data['order_note'] ?? '',
            'schedule_at' => $data['delivery_time'] ?? now(),
            'scheduled' => $data['delivery_time'] ?? 1,
            'source' => 'dashboard',
            'comment' => $data['comment'] ?? null,
            'comment_for_store' => $data['comment_for_store'] ?? null,
            'comment_for_warehouse' => $data['comment_for_warehouse'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'delivery_man_id' => $data['delivery_man_id'] ?? $order?->delivery_man_id,
        ]);


        $calcOrderAmount = 0;
        $calcTotalProductPrice = 0;

        collect($data['products'])
            ->each(function ($item) use (&$calcOrderAmount, &$calcTotalProductPrice, $order) {
                $product = Product::active()->findOrFail($item['id']);
                $product->order_count++;
                $product->save();
                $orderDetail = OrderDetail::create([
                    'product_id' => $item['id'],
                    'order_id' => $order['id'],
                    'price' => $item['price'] ?? $this->productPrice($product, $item['variation']) ?? $product->price,
                    'quantity' => $item['quantity'],
                    'variation' => $item['variation'] ? json_encode([$item['variation']]) : json_encode([]),
                    'variant' => $item['variation'] ? json_encode($item['variation']) : null,
                    'product_details' => Helpers::product_data_formatting($product, false, false, app()->getLocale()),
                    'discount' => $item['discount']
                    //                    'add_ons' => $this->make_add_ons($item['add_ons'] ?? [], $item['quantity']),
                    //                    'total_add_on_price' => $this->calcAddOns($item['add_ons'] ?? [], $item['quantity']),
                ]);
                //                $calcOrderAmount += ($orderDetail->price * $orderDetail->quantity) + $orderDetail->total_add_on_price;
                //                $calcTotalFoodPrice += $calcOrderAmount;
                $calcOrderAmount += ($orderDetail->price * $orderDetail->quantity) + $orderDetail->total_add_on_price;
                $withDiscount = $calcOrderAmount * (1 - $orderDetail->discount/100);
                $calcTotalProductPrice = $withDiscount;
            });

        $order->total_products_price = $calcTotalProductPrice;


        // Подсчет суммы доставки
        if (isset($data['delivery_charge'])) {

            $order->order_amount = $calcTotalProductPrice; //freelancer couriers delivery charge amount removed from order_amount 
            $order->delivery_charge = $data['delivery_charge'];
        } else {
            $order->order_amount = $calcTotalProductPrice; //freelancer couriers delivery charge amount removed from order_amount
        }
        $order->save();
        return $order;
    }

    public function store($data, $user)
    {


        $deliveryAddress = $data['delivery_address'];
        $address = [
            'contact_person_name' => auth()->user()->name,
            'contact_person_number' => auth()->user()->phone,
            'address' => null,
            'floor' => $deliveryAddress['floor'],
            'road' => $deliveryAddress['street'],
            'house' => $deliveryAddress['house'],
            'address_type' => $deliveryAddress['address_type'] ?? 'Delivery',
            'longitude' => (string)$deliveryAddress['longitude'],
            'latitude' => (string)$deliveryAddress['latitude'],
        ];
        $order = Order::create([
            'user_id' => $user->id,
            'order_amount' => isset($data['order_amount']) ?? 0,
            'restaurant_id' => $data['restaurant_id'],
            'payment_method' => $data['payment_method'],
            'delivery_type' => $data['delivery_type'],
            'delivery_address' => json_encode($address),
            'order_note' => $data['order_note'],
            'schedule_at' => $data['delivery_time'] ?? now(),
            'scheduled' => $data['delivery_time'] ?? 1,
            'source' => 'mobile'
        ]);

        $calcOrderAmount = 0;
        $calcTotalFoodPrice = 0;

        //создание order_details
        collect($data['items'])
            ->each(function ($item) use (&$calcOrderAmount, &$calcTotalFoodPrice, $order) {
                $product = Food::active()->findOrFail($item['id']);
                $product->order_count++;
                $product->save();
                $orderDetail = OrderDetail::create([
                    'food_id' => $item['id'],
                    'order_id' => $order['id'],
                    'price' => $this->productPrice($product, $item['variation']) ?? $product->price,
                    'quantity' => $item['qty'],
                    'variation' => $item['variation'] ? json_encode([$item['variation']]) : json_encode([]),
                    'food_details' => Helpers::product_data_formatting($product, false, false, app()->getLocale()),
                    'add_ons' => $this->make_add_ons($item['add_ons'] ?? [], $item['qty']),
                    'total_add_on_price' => $this->calcAddOns($item['add_ons'], $item['qty']),
                    'discount' => $item['discount']
                ]);
                $calcOrderAmount += ($orderDetail->price * $orderDetail->quantity) + $orderDetail->total_add_on_price;
                $withDiscount = $calcOrderAmount * (1 - $orderDetail->discount/100);
                $calcTotalProductPrice = $withDiscount;
            });

        $order->total_foods_price = $calcTotalFoodPrice;
        // Подсчет суммы доставки
        $delivery_type_price = DB::table('delivery_types')->where('name', $order->delivery_type)->first()->value;
        $order->order_amount = $calcOrderAmount + $delivery_type_price;
        $order->delivery_charge = $delivery_type_price;

        $order->save();

        if ($order->payment_method == 'bonus_discount') {
            try {
                $this->bonusDiscount($order, $user);
            } catch (\Exception $exception) {
                throw new \Exception($exception->getMessage());
            }
        }

        $this->campaignService->handleCampaign($data, $order->order_amount, $order->id);

        return $order;
    }


    public function repeatOrder($order)
    {
        try {
            DB::beginTransaction();
            $duplicateOrder = $order->replicate();
            $duplicateOrder->save();

            $order->details()->get()->each(function ($detail) use ($duplicateOrder) {
                $duplicateDetail = $detail->replicate();
                $duplicateDetail->order_id = $duplicateOrder->id;
                $duplicateDetail->save();
            });
            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();

            throw new Exception('failed to duplicate  order', 500);
        }
    }


    public function make_add_ons($addOns, $food_quantity)
    {
        if ($addOns !== null) {
            return collect($addOns)->map(function ($addOn) use ($food_quantity) {
                $addOnModel = AddOn::where('id', $addOn)->first();
                if ($addOnModel) {
                    return [
                        'id' => $addOnModel->id,
                        'name' => $addOnModel['name'],
                        'price' => $addOnModel['price'],
                        'quantity' => $food_quantity,
                    ];
                } else {
                    return null;
                }
            });
        } else {
            return json_encode([]);
        }
    }


    public function calcAddOns($addOns, $food_quantity)
    {
        if ($addOns == null) return 0;

        return collect($addOns)->reduce(function ($acc, $item) use ($food_quantity) {
            $addOn = AddOn::findOrFail($item);
            return $acc + $addOn->price * $food_quantity;
        }, 0);
    }


    private function productPrice($product, $desiredVariation)
    {

        $price = collect(json_decode($product->variations, true))->map(function ($variation) use ($desiredVariation) {
            if ($variation['type'] == $desiredVariation) {
                return $variation['price'];
            }
        })->filter()->first();

        return $price;
    }


    public function calcTotalOrder($data)
    {

        $items = collect($data['items']);
        $total = $items->reduce(function ($carry, $item) {
            $food = Food::find($item['id']);
            $foodPrice = ($this->foodPrice($food, $item['variation']) ?? $food->price) * $item['qty'];
            $addonsPrice = $this->calcAddOns($item['add_ons'], $item['qty'] ?? $item['quantity']);
            return $carry + $foodPrice + $addonsPrice;
        }, 0);
        return $total;
    }

    public function bonusDiscount($order, $user)
    {
        $loyaltyPoint = LoyaltyPoint::where('expires_at', '>', now())->get();
        if ($loyaltyPoint->isEmpty()) {
            throw new \Exception('Нет бонусов или истёк срок!');
        }

        if ($user->loyalty_point == 0) {
            throw new \Exception('У вас не достаточно бонусов');
        }

        DB::transaction(function () use ($user, $order, $loyaltyPoint) {
            // Deduct the loyalty points from the order amount
            $priceOfBonus = $this->calcValueOfPoints($user->loyalty_point, $loyaltyPoint->first());
            if ($priceOfBonus >= $order->order_amount) {
                $remainingBonus = $this->calcRemainingPoints($order->order_amount - $priceOfBonus, $loyaltyPoint->first());
                $order->order_amount = 0;
                $spentbonus = $user->loyalty_point - $remainingBonus;
                $this->createSpentBonus($user, $order, $spentbonus);
                $user->loyalty_point = $remainingBonus;
                $order->save();
                $user->save();
            } else {
                // Deduct the value of the points from the order amount
                $order->order_amount -= $priceOfBonus;
                $this->createSpentBonus($user, $order, $priceOfBonus);
                // Set the user's loyalty points to zero as all are used
                $user->loyalty_point = 0;
            }

            $user->save();

            $order->save();
        });

        return $order;
    }

    public function calcValueOfPoints($userPoints, $loyaltyPoint)
    {
        return $userPoints / $loyaltyPoint->points * $loyaltyPoint->value;
    }

    public function calcRemainingPoints($value, $loyaltyPoint)
    {
        return (abs($value) / $loyaltyPoint->value) / $loyaltyPoint->points;
    }

    private function createSpentBonus($user, $order, $spentbonus)
    {
        $spentBonus = new SpentPoints();
        $spentBonus->user_id = $user->id;
        $spentBonus->order_id = $order->id;
        $spentBonus->points = $spentbonus;
        $spentBonus->source = 'air';
        $spentBonus->save();
    }

    public function storeVendor($data): Order
    {

        $customer = isset($data['customer_id']) ? User::query()->find($data['customer_id']) : null;
        


        // dd($customer);
            // $address = [
            //     'contact_person_name' => $customer->f_name,
            //     'contact_person_number' => $customer->phone,
            //     'address' => null,
            //     'floor' => null,
            //     'road' => $data['delivery_address'],
            //     'house' => null,
            //     'address_type' => 'home',
            //     'longitude' => null,
            //     'latitude' => null,
            //     'user_id' => $customer->id,
            // ];

            // $address = CustomerAddress::query()->create($address);


        $address = [
            'contact_person_name'   => $customer?->f_name ?? ($data['contact_person_name'] ?? ''),
            'contact_person_number' => $customer?->phone ?? ($data['contact_person_number'] ?? ''),
            'address'               => null,
            'floor'                 => null,
            'road'                  => $data['delivery_address'] ?? null,
            'house'                 => null,
            'address_type'          => 'Delivery',
            'longitude'             => null,
            'latitude'              => null,
        ];

        // Get the acting vendor (works for both vendor and employee)
        $vendor = $this->getActingVendor();
        
        if (!$vendor) {
            throw new Exception('Vendor not found. Please ensure you are authenticated as a vendor or employee.');
        }

        // Get store_id from vendor
        $storeId = $vendor->store?->id;
        
        if (!$storeId) {
            throw new Exception('Store not found for this vendor.');
        }

        $order = Order::create([
            'user_id' => $customer?->id,
            // 'order_amount' => isset($data['order_amount']) ?? 0,
            'store_id' => $storeId,
            'delivery_type' => $data['delivery_type'] ?? 'standard',
            'delivery_address' => json_encode($address, JSON_UNESCAPED_UNICODE),
            'order_note' => $data['order_note'] ?? '',
            'schedule_at' => $data['delivery_time'] ?? now(),
            'scheduled' => $data['delivery_time'] ?? 1,
            'source' => 'dashboard',
            'comment' => $data['comment'] ?? null,
            'comment_for_store' => $data['comment_for_store'] ?? null,
            'comment_for_warehouse' => $data['comment_for_warehouse'] ?? null,
            'order_status' => (isset($data['is_installment']) && $data['is_installment'])
                            ? OrderStatusEnum::INSTALLMENT
                            : OrderStatusEnum::ACCEPTED,
            'accepted' => now(),
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'delivery_man_id' => $data['delivery_man_id'] ?? null,
        ]);

        // Get vendor ID from the acting vendor (works for both vendor and employee)
        $vendorId = $vendor->id;

        DB::table('order_status_histories')->insert([
            'order_status' => 'create',
            'order_id' => $order->id,
            'vendor_id' => $vendorId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        



        DB::table('order_status_histories')->insert([
            'order_status' => OrderStatusEnum::ACCEPTED,
            'order_id' => $order->id,
            'vendor_id' => $vendorId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);


        // $calcOrderAmount = 0;
        $calcTotalProductPrice = 0.0;

        collect($data['products'])->each(function ($item) use (&$calcTotalProductPrice, $order) {
                $product = Product::active()->findOrFail($item['id']);
                $product->increment('order_count');

                // Support both old 'variation' and new 'variation_id' formats
                $variationId = $item['variation_id'] ?? $item['variation'] ?? null;
                $price    = $item['price'] ?? $product->price;
                $qty      = $item['quantity'];
                $discount = (float)($item['discount'] ?? 0);
                // Default to percentage for backward compatibility: all orders created before discount_type
                // was implemented were using percentage discounts, not currency
                $discountType = $item['discount_type'] ?? 'percentage';

                $detail = OrderDetail::create([
                    'product_id'      => $item['id'],
                    'order_id'        => $order->id,
                    'price'           => $price,
                    'quantity'        => $qty,
                    'variation'       => $variationId ? json_encode([$variationId]) : json_encode([]),
                    'product_details' => Helpers::product_data_formatting($product, false, false, app()->getLocale()),
                    'discount'        => $discount,
                ]);

                $line = ($price * $qty) + ($detail->total_add_on_price ?? 0);
                
                // Calculate discount based on type
                if ($discountType === 'currency') {
                    // Currency discount: subtract directly from line total
                    $lineAfterDiscount = max(0, $line - ($discount * $qty));
                } else {
                    // Percentage discount: apply percentage to line total
                    $lineAfterDiscount = $line * (1 - $discount / 100);
                }

                $calcTotalProductPrice += $lineAfterDiscount;
            });
            $order->total_products_price = $calcTotalProductPrice;

            $neworder = Order::query()->findOrFail($order->id);

            $newOrder = $order->fresh(['details']);

            // dd($newOrder);

        
            // if (isset($data['delivery_charge'])) {

            //     $newOrder->order_amount = $calcOrderAmount + $data['delivery_charge'];
            //     $newOrder->delivery_charge = $data['delivery_charge'];
            // }
            // else {
            //     $delivery_type_price = DB::table('delivery_types')->where('name', $newOrder->delivery_type)->first()->value;
            //     $newOrder->order_amount = $calcOrderAmount + $delivery_type_price;
            //     $newOrder->delivery_charge = $delivery_type_price;
            // }

            $deliveryCharge = $data['delivery_charge']
                ?? DB::table('delivery_types')
                    ->where('name', $order->delivery_type)
                    ->value('value')
                ?? 0;
            // $newOrder->order_amount = $calcOrderAmount + $newOrder->delivery_charge;


            DB::table('orders')
            ->where('id', $order->id)
            ->update([
                'order_amount'   => $calcTotalProductPrice, //removed addition of + $deliveryCharge, coz it leads to adding delivery amount to total amount but our couriers work as a freelancer it must not add up
                'delivery_charge'=> $deliveryCharge,
            ]);

            // $newOrder->update();

        return $newOrder->fresh(['details']);
    }

    public function updateVendor($data, $customer, $order)
    {
        $address = [
            'contact_person_name'   => $customer?->f_name ?? '',
            'contact_person_number' => $customer?->phone ?? '',
            'address'               => null,
            'floor'                 => null,
            'road'                  => $data['delivery_address'] ?? null,
            'house'                 => null,
            'address_type'          => 'Delivery',
            'longitude'             => null,
            'latitude'              => null,
        ];
        $order->details()->delete();

        $order->update([
            'store_id' => $data['store_id'] ?? $order->store_id,
            'delivery_type' => $data['delivery_type'] ?? 'standard',
            'delivery_address' => json_encode($address),
            'order_note' => $data['order_note'] ?? '',
            'schedule_at' => $data['delivery_time'] ?? now(),
            'scheduled' => $data['delivery_time'] ?? 1,
            'source' => 'dashboard',
            'comment' => $data['comment'] ?? null,
            'comment_for_store' => $data['comment_for_store'] ?? null,
            'comment_for_warehouse' => $data['comment_for_warehouse'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'delivery_man_id' => $data['delivery_man_id'] ?? $order?->delivery_man_id,
        ]);


        // $calcOrderAmount = 0;
        $calcTotalProductPrice = 0.0;

        collect($data['products'])->each(function ($item) use (&$calcTotalProductPrice, $order) {
                $product = Product::active()->findOrFail($item['id']);

                // On update, incrementing order_count will inflate stats.
                // $product->increment('order_count');

                // Support both old 'variation' and new 'variation_id' formats
                $variationId = $item['variation_id'] ?? $item['variation'] ?? null;
                $price    = $item['price'] ?? $product->price;
                $qty      = $item['quantity'];
                $discount = (float)($item['discount'] ?? 0);
                // Default to percentage for backward compatibility: all orders created before discount_type
                // was implemented were using percentage discounts, not currency
                $discountType = $item['discount_type'] ?? 'percentage';

                $detail = OrderDetail::create([
                    'product_id'       => $item['id'],
                    'order_id'         => $order->id,
                    'price'            => $price,
                    'quantity'         => $qty,
                    'variation'        => $variationId ? json_encode([$variationId]) : json_encode([]),
                    'variant'          => $variationId ? json_encode($variationId) : null, // if you need both, keep; else standardize to one
                    'product_details'  => Helpers::product_data_formatting($product, false, false, app()->getLocale()),
                    'discount'         => $discount,
                ]);

                $addOns = $detail->total_add_on_price ?? 0;
                $line   = ($price * $qty) + $addOns;
                
                // Calculate discount based on type
                if ($discountType === 'currency') {
                    // Currency discount: subtract directly from line total
                    $lineAfterDiscount = max(0, $line - ($discount * $qty));
                } else {
                    // Percentage discount: apply percentage to line total
                    $lineAfterDiscount = $line * (1 - ($discount / 100));
                }

                $calcTotalProductPrice += $lineAfterDiscount;
            });

        $order->total_products_price = $calcTotalProductPrice;


        // Подсчет суммы доставки
        if (array_key_exists('delivery_charge', $data)) {
            $order->delivery_charge = $data['delivery_charge'] ?? 0;
        }
        $order->order_amount = $calcTotalProductPrice;
        $order->save();
        return $order->fresh(['details']);
    }
}
