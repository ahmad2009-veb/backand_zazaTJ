<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Finance\ProductProfitabilityResource;
use App\Models\Product;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function __construct(public FinanceService $financeService) {}
    public function productProfitability(Request $request)
    {
        $search = $request->input('search');
        $products = Product::with('warehouse')
            ->when($search, function ($query, $search) {
                // Optionally, search for products by name
                $query->where('name', 'LIKE', '%' . $search . '%');
            })->paginate($request->per_page ?? 10);

        return ProductProfitabilityResource::collection($products);
    }

    public function mainIncomeStatistics()
    {
        $authAdmin = auth()->user();
        $data =   $this->financeService->mainIncomeStatistics('admin', $authAdmin);
        return response()->json($data);
    }




    public function monthlyFinanceStatistics()
    {
        $authAdmin = auth()->user();
        $data = $this->financeService->monthlyFinanceStatistics('admin', $authAdmin);
        return response()->json($data);
    }
}
