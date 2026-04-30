<?php

namespace App\Repositories\Admin;

use App\Models\DeliveryMan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DeliveryManRepository
{
    public function getTopDeliverymen(?int $zoneId = null, int $count = 10, ?string $duration = null): Collection
    {
        return DeliveryMan::when(is_numeric($zoneId), function ($q) use ($zoneId) {
                return $q->where('zone_id', $zoneId);
            })
            ->where('type', 'zone_wise')
            ->withCount(['orders' => function ($query) use ($duration) {
                $query->where('order_status', 'delivered');

                match ($duration) {
                    'today' => $query->whereDate('created_at', Carbon::today()),
                    'week'  => $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
                    'month' => $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]),
                    default => null,
                };
            }])
            ->orderByDesc("order_count", 'desc')
            ->take($count)
            ->get();
    }
}
