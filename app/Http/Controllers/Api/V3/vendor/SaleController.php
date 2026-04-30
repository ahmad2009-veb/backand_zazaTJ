<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Enums\SaleStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\sale\SaleStoreRequest;
use App\Http\Requests\Api\v3\admin\sale\SaleUpdateRequest;
use App\Http\Resources\Admin\Sale\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    private $saleService;
    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    public function index(Request $request)
    {
        $search = $request->search;

        $sales =  auth()->user()->sales()
            ->with(['user', 'warehouse', 'order', 'warehouseTransfer.toVendor', 'saleProducts'])
            ->when($search, function ($query, $search) {
                return $query->where('sales.name', 'LIKE', '%' . $search . '%')->orWhere('sales.id', 'LIKE', '%' . $search . '%');
            })->latest()->paginate($request->per_page ?? 10);
        return SaleResource::collection($sales);
    }

    public function store(SaleStoreRequest $request)
    {
        $data = $request->validated();

        $this->saleService->StoreSale($data);


        return response()->json(['message' => 'Реализация создана успешно']);
    }

    public function update(SaleUpdateRequest $request, Sale $sale)
    {

        $data =  $request->validated();
        try {
            $this->saleService->updateSale($data, $sale);
            return response()->json(['message' => 'Реализация успешно обновлена']);
        } catch (\Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    public function show(Sale $sale)
    {
        return SaleResource::make($sale->load([
            'saleProducts',
            'user',
            'warehouse',
            'order.orderInstallment',
            'warehouseTransfer.toVendor',
            'warehouseTransfer.installment'
        ]));
    }

    public function toggleStatus(Request $request, Sale $sale)
    {
        $request->validate(['status' => 'required|string|in:pending,completed']);

        $sale->status = $request->status;
        $sale->save();
        return response()->json(['message' => 'Статус успешно изменен']);
    }

    public function delete(Sale $sale) {
        $sale?->transaction()->delete();
        $sale->delete();
        return response()->json(['message' => 'Удалено успешно']);
    }
}
