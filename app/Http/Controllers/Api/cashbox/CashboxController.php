<?php

namespace App\Http\Controllers\Api\cashbox;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\Cashbox\OrderRefundRequest;
use App\Http\Requests\Api\v3\CashboxOrderRequest;
use App\Http\Resources\ProductItemResource;
use App\Http\Resources\Cashbox\OrderResource;
use App\Http\Resources\Cashbox\OrdersInvoceResource;
use App\Models\AddOn;
use App\Models\Cashbox;
use App\Models\Food;
use App\Models\LoyaltyPoint;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SpentPoints;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashboxController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function makeOrder(CashboxOrderRequest $request)
    {
        $cashier = auth()->user();
        $cashbox = Cashbox::query()->where(['restaurant_id' => $cashier->restaurant->id])->first();
        try {
            DB::beginTransaction();
            $order = new Order();

            $order->payment_method = $request->payment_method;
            $order->restaurant_id = $cashbox->restaurant_id;
            $order->coupon_discount_amount = $request->discount ?? 0;
            $order->discount_type = $request->discount_type;
            $order->delivery_type = 'cashbox';
            $order->order_type = 'cashbox';
            $order->source = 'cashbox';
            $order->cashbox_id = $cashbox->id;
            $order->payment_status = 'paid';
            $order->fd_data = json_encode($request->fd_data);
            $order->save();


            $items = collect($request->items);
            $calcOrderAmount = 0;


            $items->each(function ($item) use ($order, &$calcOrderAmount) {
                $this->processOrderItem($item, $order, $calcOrderAmount);
            });


            $order->order_amount = $calcOrderAmount;
            $order->save();
            if ($order->payment_method == 'bonus_discount') {
                $user = User::find($request->user_id);

                try {
                    $this->bonusDiscount($order, $user, $request->take_bonus);
                } catch (\Exception $exception) {
                    throw new \Exception($exception->getMessage());
                }

            }

            if ($request->discount_for_order) {
                $discType = $request->input('discount_type');
                $discValue = $request->input('discount');
                switch ($discType) {
                    case "percent":
                        $percentageValue = $order->order_amount * ($discValue / 100);
                        $order->coupon_discount_amount = $percentageValue;
                        $order->save();
                        break;
                    case "fixed":
                        $order->coupon_discount_amount = $discValue;
                        $order->save();
                        break;
                }
            }


            DB::commit();

            return response()->json(['message' => 'created successfully', 'order' => $order], 201);
        } catch
        (\Exception $exception) {
            DB::rollBack();
            return response()->json(['message' => $exception->getMessage(), 'line' => $exception->getLine()], 400);
        }


    }

    public function getFoodsByIds(Request $request)
    {


        $foodIds = $request->food_ids;
        $foods = Food::query()->whereIn('id', $foodIds)->get();

        return ProductItemResource::collection($foods);
    }

    public function foodPrice($food, $desiredVariation)
    {

        $price = collect(json_decode($food->variations, true))->map(function ($variation) use ($desiredVariation) {
            if ($variation['type'] == $desiredVariation) {
                return $variation['price'];
            }

        })->filter()->first();

        return $price ?? $food->price;
    }

    public function make_add_ons($addOns)
    {
        if ($addOns !== null) {
            return collect($addOns)->map(function ($addOn) {
                $addOnModel = AddOn::where('id', $addOn['id'])->first();
                if ($addOnModel) {
                    return [
                        'id' => $addOn['id'],
                        'name' => $addOnModel['name'],
                        'price' => $addOnModel['price'],
                        'quantity' => $addOn['qty'],
                    ];

                } else {
                    return null;
                }
            });
        } else {
            return json_encode([]);
        }
    }

    public function calcAddOns($addOns)
    {
        if ($addOns == null) return 0;

        return collect($addOns)->reduce(function ($acc, $item) {
            $addOn = AddOn::findOrFail($item['id']);
            return $acc += $addOn->price * $item['qty'];
        }, 0);

    }

    public function bonusDiscount($order, $user, $takeBonus)
    {
        $loyaltyPoint = LoyaltyPoint::where('expires_at', '>', now())->get();
        if ($loyaltyPoint->isEmpty()) {
            throw new \Exception('Нет бонусов или истёк срок!', 400);

        }

        if ($user->loyalty_point == 0) {
            throw new \Exception('У вас не достаточно бонусов', 400);

        }

        DB::transaction(function () use ($user, $order, $loyaltyPoint, $takeBonus) {
            // Deduct the loyalty points from the order amount
            $priceOfBonus = $this->calcValueOfPoints($takeBonus, $loyaltyPoint->first());
//            dd($priceOfBonus);

            $remainingBonus = $this->calcRemainingPoints($user->loyalty_point - $priceOfBonus, $loyaltyPoint->first());
//            dd($remainingBonus);
            $order->bonus_discount_amount = $priceOfBonus;
            $spentbonus = $user->loyalty_point - $remainingBonus;
            $this->createSpentBonus($user, $order, $spentbonus);
            $user->loyalty_point = $remainingBonus;

            $order->save();
            $user->save();

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

    public function handleFoodPrice($product, $variation, $disValue, $disType)
    {
        $variationPrice = $this->foodPrice($product, $variation);
        switch ($disType) {
            case 'percent':
                $discAmount = $variationPrice * ($disValue / 100);
                break;
            case 'fixed':
                $discAmount = $disValue;
                break;
            default:
                // No discount or unrecognized discount type
                $discAmount = 0;
                break;
        }
        $finalPrice = max(0, $variationPrice - $discAmount);
        return $finalPrice;

    }

    public function getInvoices(Request $request)
    {
        $user = $request->user();

        $cashbox = Cashbox::query()->where(['restaurant_id' => $user->restaurant_id])->first();

        if ($cashbox) {
            $today = now()->startOfDay();
            $orders = $cashbox->orders()->where('created_at', '>=', $today)->paginate($request->per_page);
            return OrdersInvoceResource::collection($orders);
        }

        return response()->json(['message' => 'cashbox not found'], 404);
    }

    public function getDetail(Order $order)
    {

        return OrderResource::make($order);
    }


    public function refundPartial(OrderRefundRequest $request, Order $order)
    {
        DB::beginTransaction();
        try {
            $items = collect($request->input('items'));

            $calcOrderAmount = 0;


            if ($order->discount_type == 'fixed') {
                $this->handleRefundOrderDiscount($order->coupon_discount_amount, $order->details, $items, $order);

            }
            $order->details()->delete();
            $items->each(function ($item) use ($order, &$calcOrderAmount) {
                $this->processOrderItem($item, $order, $calcOrderAmount);
            });
            $order->order_amount = $calcOrderAmount;
            $currentFdData = json_decode($order->fd_data, true);
            $currentFdData[] = $request->fd_data;
            $order->fd_data = $currentFdData;

            $order->save();


            DB::commit();
            return response()->json(['message' => 'order edited successfull'], 201);
        } catch (\Exception $exception) {

            DB::rollBack();
        }
    }


    public function refundFull(Order $order)
    {
        $order->refunded = now();
        $order->order_status = 'refunded';
        $order->save();
        return response()->json(['message' => 'order refunded successfully'], 201);
    }

    private function processOrderItem(array $item, Order $order, float &$calcOrderAmount): void
    {
        $product = Food::active()->findOrFail($item['id']);
        $product->order_count++;
        $product->save();
        $orderDetail = new OrderDetail();
        $orderDetail->food_id = $item['id'];
        $orderDetail->order_id = $order->id;
        $orderDetail->quantity = $item['qty'];
        $orderDetail->price = $this->handleFoodPrice($product, $item['variation'], $item['discount'], $item['discount_type']);
        $orderDetail->add_ons = $this->make_add_ons($item['add_ons']);
        $orderDetail->food_details = Helpers::product_data_formatting($product, false, false, app()->getLocale());
        $orderDetail->variation = $item['variation'] ? json_encode([$item['variation']]) : json_encode([]);
        $orderDetail->variant = $item['variation'] ? json_encode($item['variation']) : null;
        $orderDetail->discount_on_food = $item['discount'] ?? null;
        $orderDetail->discount_type = $item['discount_type'];
        $orderDetail->total_add_on_price = $this->calcAddOns($item['add_ons']);
        $orderDetail->save();
        $calcOrderAmount += ($orderDetail->price * $orderDetail->quantity) + $orderDetail->total_add_on_price;
    }

    private function createSpentBonus($user, $order, $spentbonus)
    {
        $spentBonus = new SpentPoints();
        $spentBonus->user_id = $user->id;
        $spentBonus->order_id = $order->id;
        $spentBonus->points = $spentbonus;
        $spentBonus->source = 'cashbox';
        $spentBonus->save();
    }

    public function getLastFd(Order $order) {
      $fdData = json_decode($order->fd_data,true);
      return end($fdData);
    }


    private function handleRefundOrderDiscount($discount, $details, $items, $order)
    {
        $itemsIds = $items->pluck('id');
        $detailsIds = $details->pluck('food_id');
        $missingFoodIds = $detailsIds->diff($itemsIds);
        $result = $missingFoodIds->all();
        $details_for_delete = $details->whereIn('food_id', $result);
        $resDiscount = $discount;
        $details_for_delete->each(function ($el) use (&$resDiscount, $discount) {
            $resDiscount -= ($el->price + $el->total_add_on_price);
            if ($resDiscount < 0) {
                $resDiscount = $discount;
                return false;
            }

        });

        $order->coupon_discount_amount = $resDiscount;
        $order->save();
    }

}
