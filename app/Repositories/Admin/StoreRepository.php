<?php

namespace App\Repositories\Admin;

use App\Models\Store;
use Illuminate\Support\Collection;

class StoreRepository
{
    public function getTopStores(?int $zoneId = null): Collection
    {
        return Store::when(is_numeric($zoneId), function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            })
            ->orderByDesc("order_count", 'desc')
            ->get();
    }
}
