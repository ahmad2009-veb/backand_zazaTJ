<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\sale\SaleStoreRequest;
use App\Http\Requests\Api\v3\admin\sale\SaleUpdateRequest;
use App\Http\Resources\Admin\Sale\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    private  $saleService;
    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $sales = Sale::query()->when($search, function ($query, $search) {
            return $query->where('name', 'LIKE', '%' . $search . '%')->orWhere('id', 'LIKE', '%' . $search . '%');
        })->latest()->paginate($request->per_page ?? 10);

        return SaleResource::collection($sales);
    }

    public function store(SaleStoreRequest $request)
    {
        $data = $request->validated();

        $this->saleService->StoreSale($data);
        return response()->json(['message' => 'created successfully']);
    }

    public function show(Sale $sale)
    {
        return SaleResource::make($sale->load('saleProducts', 'delivery_man'));
    }

    public function toggleStatus(Request $request, Sale $sale)
    {
        $request->validate(['status' => 'required|string|in:pending,completed,refunded']);

        $sale->status = $request->status;
        $sale->save();
        return response()->json(['message' => 'Статус успешно изменен']);
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
}
