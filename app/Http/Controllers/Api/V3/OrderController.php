<?php

namespace App\Http\Controllers\Api\V3;

use App\Events\CustomerPointStatus;
use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\CartItemsRequest;
use App\Http\Requests\Api\v3\GetCartItems;
use App\Http\Requests\Api\v3\GetTotalOrderCartRequest;
use App\Http\Requests\Api\v3\OrderRequest;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\OrderResoursce;
use App\Http\Resources\UserOrderFoodResource;
use App\Models\Food;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\OrderService;
use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{

    public RestaurantService $restaurantService;
    public OrderService $orderService;

    public function __construct(RestaurantService $restaurantService, OrderService $orderService)
    {
        $this->restaurantService = $restaurantService;
        $this->orderService = $orderService;
    }

    public function getCustomerOrders(Request $request): JsonResource
    {
        $user = $request->user();
        $userOrders = $user->orders()->with('details.food')->orderByDesc('id')->paginate($request->per_page ?? 10);
        return OrderResoursce::collection($userOrders);
    }

    public function makeOrder(OrderRequest $request)
    {

        $validated = $request->validated();


        $deliveryTime = $request->input('delivery_time');
        try {
            DB::beginTransaction();
            $restaurant = Restaurant::active()
                ->opened()
                ->findOrFail($validated['restaurant_id']);
            //Проверка доставки заказа в указанное время заказа.
            if ($validated['delivery_type'] == 'express') {
                $isOpened = $this->restaurantService->isOpenedOnDeliveryTime($deliveryTime, $restaurant->available_time_starts, $restaurant->available_time_ends);
                if (!$isOpened) {
                    return response()->json(['message' => "К сожалению не сможем доставить. Выберите другое время"]);
                }
            }

            // Проверка доступности ресторана во время заказа
            if ($this->restaurantService->canOrder($restaurant->available_time_starts, $restaurant->available_time_ends)) {

                // сохранение заказа в БД

                $order = $this->orderService->store($validated, auth()->user());
                DB::commit();
                return $order;
            }

            return response()->json(['restaurant closed']);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json(["message" => $exception->getMessage(), 'line' => $exception->getLine()], 400);
        }
    }

    public function repeatOrder(Order $order): JsonResponse
    {
        try {
            $repeatOr = $this->orderService->repeatOrder($order);
            return response()->json(['message' => 'Order repeated successfully'], 201);
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], $ex->getCode(), 404);
        }
    }


    public function getRestaurantOrders(Restaurant $restaurant, Request $request): JsonResource
    {

        $user = $request->user();
        $foods = $user->orders()->where('restaurant_id', $restaurant->id)
            ->with('details.food')
            ->get()
            ->pluck('details.*.food')
            ->flatten()
            ->unique('id');

        return UserOrderFoodResource::make($foods);

    }

    public function getTotalOrderCart(GetTotalOrderCartRequest $request)
    {
       $data = $request->validated();
       $restaurant = Restaurant::query()->find($request->input('restaurant_id'));

        return response()->json([
            'price' =>  $this->orderService->calcTotalOrder($data),
            'time' => $restaurant->delivery_time,
        ]);
    }

    public function getCartItems(CartItemsRequest $request)
    {
        $food_ids = array_unique(array_map(function ($item) {
            return $item['id'];
        }, $request->foods));
        $foods = Food::query()->whereIn('id', $food_ids)->get();
        $cart_items = array_map(function ($cartItem) use ($foods, $request) {
            $food = $foods->find($cartItem['id']);
            if ($food === null) {
                return null;
            }
            return CartItemResource::make($food)->additional($cartItem);
        }, $request->foods);
        $data = array_filter($cart_items, fn($item) => $item !== null);
        return response()->json([
            'data' => $data
        ]);
    }

    public function makeOrderPaid(Order $order)
    {

        if ($order->payment_status !== 'paid') {
            $order->payment_status = 'paid';
            $order->customer_point->status = 1;
            $order->customer_point->save();
            $order->save();
            event(new OrderPaid($order, 'paid'));
            return response()->json(['message' => true], 201);
        }

        return response()->json(['message' => 'already added'], 404);
    }
}

