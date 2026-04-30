<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Models\Zone;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use App\Http\Resources\Admin\AdminFoodsResource;
use App\Http\Resources\Admin\TopClientsResource;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service){}

    public function getZones()
    {
        $zones = Zone::query()->get()->map(function ($el) {
            return [
                'id' => $el['id'],
                'name' => $el['name']
            ];
        });

        return response()->json($zones, 200);
    }

    public function statistics(Request $request)
    {
        return $this->service->getStatistics($request->input('duration'));
    }


    public function orderStatistics(Request $request)
    {
        $request->validate([
            'zone_id' => 'required',
            'statistics_type' => 'required|string'
        ]);
        $statisticsType = $request->input('statistics_type');
        $zoneId = $request->input('zone_id');

        return $this->service->getOrderStatistics($statisticsType, $zoneId);
    }

    public function totalSellStatistics()
    {
        return $this->service->getTotalSellStatistics();
    }

    public function lastOrders(Request $request)
    {
        $count = $request->input('count', 5);
        return $this->service->getLastOrders($count);
    }


    public function topStores(Request $request)
    {
        $zoneId = $request->input('zone_id');
        return $this->service->getTopStores($zoneId);
    }

    public function topProducts(Request $request)
    {
        $zoneId = $request->input('zone_id');
        $count = $request->input('count', 10);

        return $this->service->getTopProducts($zoneId, $count);
    }

    public function topDeliveryman(Request $request)
    {
        $validated = $request->validate([
            'zone_id' => 'nullable',
            'count' => 'nullable|numeric|between:1,100',
            'duration' => 'nullable|in:today,week,month',
        ]);
        return $this->service->getTopDeliverymen(
                    zoneId: $validated['zone_id'] ?? null,
                    count: $validated['count'] ?? 10,
                    duration: $validated['duration'] ?? null,
                );
    }

    public function adminFoods(Request $request)
    {
        $validated = $request->validate([
            'duration' => 'nullable|in:today,week,month',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $duration = $validated['duration'] ?? null;
        $perPage = $validated['per_page'] ?? 15;

        $admins = $this->service->getAdminFoods($duration, $perPage);

        return AdminFoodsResource::collection($admins);
    }

    public function deliverymanOrders(DeliveryMan $deliveryMan, Request $request)
    {
        $request->validate([
            'duration' => 'required|string|in:all_time,today,week,month'
        ]);
        $duration = $request->input('duration');
        return $deliveryMan->filterOrdersByDuration($duration)->get();
    }

    public function topClients()
    {
        $clients = $this->service->getTopClients();
        return TopClientsResource::collection($clients);
    }
}
