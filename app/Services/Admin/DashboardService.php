<?php

namespace App\Services\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderTransaction;
use Illuminate\Support\Facades\DB;
use App\Repositories\Admin\OrderRepository;
use App\Repositories\Admin\StoreRepository;
use App\Repositories\Admin\ProductRepository;
use App\Http\Resources\Admin\TopClientsResource;
use App\Repositories\Admin\DeliveryManRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardService{

    public function __construct(
        private OrderRepository $orderRepository,
        private StoreRepository $storeRepository,
        private ProductRepository $productRepository,
        private DeliveryManRepository $deliveryManRepository
    ) {}
    
    public function getStatistics(string $duration): array
    {
        return [
            'pending' => Order::statusStatistics('pending', $duration),
            'picked_up' => Order::statusStatistics('picked_up', $duration),
            'delivered' => Order::statusStatistics('delivered', $duration),
            'canceled' => Order::statusStatistics('canceled', $duration),
        ];
    }

    public function getOrderStatistics(string $statisticsType, $zoneId): array
    {
        return $this->orderRepository->getScopedCounts($statisticsType, $zoneId);
    }

    public function getTotalSellStatistics(): array
    {
        $total_sell = [];
        for ($i = 1; $i <= 12; $i++) {
            $total_sell[$i] = OrderTransaction::NotRefunded()
                ->whereMonth('created_at', $i)
                ->whereYear('created_at', now()->year)
                ->sum('order_amount');
        }

        $year = OrderTransaction::NotRefunded()
            ->whereYear('created_at', now()->year)
            ->sum('order_amount');

        $month = OrderTransaction::NotRefunded()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('order_amount');

        $today = OrderTransaction::NotRefunded()
            ->whereDate('created_at', now()->toDateString())
            ->sum('order_amount');

        return [
            'total_sell' => $total_sell,
            'year' => $year,
            'month' => $month,
            'today' => $today,
        ];
    }

    public function getLastOrders(int $count = 5): array
    {
        $orders = $this->orderRepository->getLatestOrders($count);

        return $orders->map(function ($ord) {
            $customer = $ord->customer;
            return [
                'id' => $ord['id'],
                'order_amount' => $ord['order_amount'],
                'created_at' => $ord['created_at'],
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'image' => url('storage/profile/' . $customer->image)
                ]
            ];
        })->toArray();
    }

    public function getTopStores(?int $zoneId = null): array
    {
        $stores = $this->storeRepository->getTopStores($zoneId);

        return $stores->map(function ($res) {
            return [
                'id' => $res['id'],
                'name' => $res['name'],
                'address' => $res['address'],
                'order_count' => $res['order_count'],
                'logo' => $res['logo'] ? url('storage/restaurant/' . $res['logo']) : null
            ];
        })->toArray();
    }

    public function getTopProducts(?int $zoneId = null, int $count = 10): array
    {
        $products = $this->productRepository->getTopProducts($zoneId, $count);

        return $products->map(function ($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'image' => $product['image'] ? url('storage/product/' . $product['image']) : null,
                //  'store' => $product->store->name,
                'order_count' => $product['order_count'],
            ];
        })->toArray();
    }

    public function getTopDeliverymen(?int $zoneId = null, int $count = 10, ?string $duration = null): array
    {
        $deliverymen = $this->deliveryManRepository->getTopDeliverymen($zoneId, $count, $duration);

        return $deliverymen->map(function ($dm) {
            return [
                'id' => $dm->id,
                'name' => $dm->f_name,
                'l_name' => $dm->l_name,
                'image' => $dm->image ? url('storage/delivery-man' . $dm->image) : null,
                'phone' => $dm->phone,
                'order_count' => $dm->orders_count,
            ];
        })->toArray();
    }

    public function getAdminFoods(?string $duration, int $perPage)
    {
        return Admin::withCount(['order_histories' => function ($query) use ($duration) {
                $query->where('order_status', 'confirmed');

                if ($duration === 'today') {
                    $query->whereDate('created_at', Carbon::today());
                } elseif ($duration === 'week') {
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                } elseif ($duration === 'month') {
                    $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                }
            }])
            ->whereHas('role', fn($q) => $q->where('name', '!=', 'Master Admin'))
            ->paginate($perPage);
    }

    public function getTopClients(): AnonymousResourceCollection
    {
        return User::query()
            ->whereHas('orders', fn ($q) => $q->where('order_status', 'delivered'))
            ->withCount([
                'orders as orders_amount_count' => fn ($q) =>
                    $q->where('order_status', 'delivered')
                    ->select(DB::raw('SUM(order_amount)')) // Sum of order_amount for delivered orders
            ])
            ->orderByDesc('orders_amount_count') // Order users by total order amount in descending order
            ->limit(5)
            ->get();
    }
}