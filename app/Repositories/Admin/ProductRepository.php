<?php

namespace App\Repositories\Admin;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductRepository
{
    public function getTopProducts(?int $zoneId = null, int $count = 10): Collection
    {
        return Product::withoutGlobalScopes()
            ->when(is_numeric($zoneId), function ($q) use ($zoneId) {
                $q->whereHas('store', function ($query) use ($zoneId) {
                    $query->where('zone_id', $zoneId);
                });
            })
            ->orderByDesc("order_count", 'desc')
            ->take($count)
            ->get();
    }
}