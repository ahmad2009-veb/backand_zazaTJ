<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\Order;
use App\Models\OrderTransaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public  function statistics(Request $request) {
        $vendor = auth()->user();
        $store = $vendor->store;
        $duration = $request->input('duration'); // today, week, month, year, all_time
        $pending = Order::statusStatistics('pending', $duration, $store->id);
        $picked_up = Order::statusStatistics('picked_up', $duration, $store->id);
        $delivered = Order::statusStatistics('delivered', $duration, $store->id);
        $canceled = Order::statusStatistics('canceled', $duration, $store->id);

        return [
            'pending' => $pending,
            'picked_up' => $picked_up,
            'delivered' => $delivered,
            'canceled' => $canceled
        ];
    }

    public function totalSellStatistics(Request $request) {
        $vendor = auth()->user();
        $total_sell = [];
        for ($i = 1; $i <= 12; $i++) {
            $total_sell[$i] = OrderTransaction::NotRefunded()
                ->where('vendor_id', $vendor->id)
                ->whereMonth('created_at', $i)->whereYear('created_at', now()->format('Y'))
                ->sum('order_amount');
        }
        $year = OrderTransaction::NotRefunded()
            ->where('vendor_id', $vendor->id)
            ->whereYear('created_at', now()->format('Y'))
            ->sum('order_amount');
        $month = OrderTransaction::NotRefunded()
            ->where('vendor_id', $vendor->id)
            ->whereMonth('created_at', now()->format('m'))
            ->whereYear('created_at', now()->format('Y'))
            ->sum('order_amount');
        $today = OrderTransaction::NotRefunded()
            ->where('vendor_id', $vendor->id)
            ->whereDate('created_at', now()->format('Y-m-d'))
            ->sum('order_amount');

        return response()->json([
            'total_sell' => $total_sell,
            'year' => $year,
            'month' => $month,
            'today' => $today,
        ]);
    }

    public function lastOrders(Request $request)
    {
        $count = $request->input('count', 5);

        $vendor = auth()->user();
        $store = $vendor->store;
        return Order::query()->where('store_id', $store->id)->orderBy('schedule_at', 'desc')->take($count)->get()
            ->map(function ($or) {
                $customer = $or->customer;
                return [
                    'id' => $or['id'],
                    'order_amount' => $or['order_amount'],
                    'created_at' => $or['created_at'],
                    'customer' => $customer ? [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'image' => url('storage/profile/' . $customer->image)
                    ] : null,
                ];
            });
    }

    public function topFoods(Request $request)
    {
        $zoneId = $request->input('zone_id');
        $count = $request->input('count', 3);
        $vendor = auth()->user();
        $restaurant = $vendor->restaurants[0];
        $top_sell = Food::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->when(is_numeric($zoneId), function ($q) use ($zoneId) {
                return $q->whereHas('restaurant', function ($query) use ($zoneId) {
                    return $query->where('zone_id', $zoneId);
                });
            })
            ->orderBy("order_count", 'desc')
            ->take($count)
            ->get()
            ->map(function ($food) {
                return [
                    'id' => $food['id'],
                    'name' => $food['name'],
                    'image' => $food['image'] ? url('storage/product/' . $food['image']) : null,
                    'restaurant' => $food->restaurant->name,
                    'order_count' => $food['order_count'],
                ];
            });

        return $top_sell;
    }
}
