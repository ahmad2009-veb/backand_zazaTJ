<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\EmployeeRoleResource;
use App\Models\EmployeeRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeRoleController extends Controller
{
    public function index()
    {
        $vendor = auth()->user();
        $store = $vendor->store()->first();
        return EmployeeRoleResource::collection($store->employeeRoles);
    }

    /**
     * Get available modules/pages for employee permissions
     */
    public function availableModules(): JsonResponse
    {
        $modules = [
            ['key' => 'pos-terminal', 'name' => 'Создать новый заказ'],
            ['key' => 'orders', 'name' => 'Заказы'],
            // ['key' => 'chats', 'name' => 'Чаты'],
            ['key' => 'warehouse', 'name' => 'Склад'],
            ['key' => 'finance', 'name' => 'Финансы'],
            ['key' => 'couriers', 'name' => 'Курьеры'],
            ['key' => 'customers', 'name' => 'Клиенты']
        ];

        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }
}
