<?php

namespace App\Repositories\Admin;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OrderRepository
{
    public function getScopedCounts(string $statisticsType, $zoneId = null): array
    {
        $scopes = [
            'searching_for_dm' => Order::SearchingForDeliveryman(),
            'accepted_by_dm'   => Order::AccepteByDeliveryman(),
            'preparing_in_rs'  => Order::Preparing(),
            'picked_up'        => Order::FoodOnTheWay(),
            'delivered'        => Order::Delivered(),
            'canceled'         => Order::Canceled(),
            'refunded'         => Order::Refunded(),
        ];

        if ($statisticsType === 'today') {
            $scopes['refund_requested'] = Order::RefundRequested();
        } else {
            $scopes['refund_cancelled'] = Order::failed();
        }

        if ($statisticsType === 'today') {
            $scopes['searching_for_dm']->whereDate('created_at', Carbon::now());
            $scopes['accepted_by_dm']->whereDate('accepted', Carbon::now());
            $scopes['preparing_in_rs']->whereDate('processing', Carbon::now());
            $scopes['picked_up']->whereDate('picked_up', Carbon::now());
            $scopes['delivered']->whereDate('delivered', Carbon::now());
            $scopes['canceled']->whereDate('canceled', Carbon::now());
            $scopes['refund_requested']->whereDate('refund_requested', Carbon::now());
            $scopes['refunded']->whereDate('refunded', Carbon::now());
        }

        foreach ($scopes as $key => $query) {
            $query->Notpos();

            if ($key === 'searching_for_dm') {
                $query->OrderScheduledIn(30);
            }

            if (is_numeric($zoneId)) {
                $query->where('zone_id', $zoneId);
            }

            $scopes[$key] = $query->count();
        }

        return $scopes;
    }

    public function getLatestOrders(int $count = 5): Collection
    {
        return Order::query()
            ->with('customer') // eager load to avoid N+1, needs to be retested
            ->orderByDesc('schedule_at', 'desc')
            ->take($count)
            ->get();
    }
}
