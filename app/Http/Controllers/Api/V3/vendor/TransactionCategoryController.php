<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionCategoryResource;
use App\Http\Resources\Vendor\TransactionSubcategoryResource;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Traits\VendorEmployeeAccess;
use Illuminate\Support\Facades\DB;

class TransactionCategoryController extends Controller
{
    use VendorEmployeeAccess;
    public function index(Request $request)
    {
        $search = $request->search;

        $categories =  $this->getActingVendor()->transactionCategories()
            ->when($search, fn($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->select('id', 'name', 'parent_id')->paginate($request->per_page ?? 10);
        return TransactionCategoryResource::collection($categories);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $vendor = $this->getActingVendor();
            $request->validate([
                'name' => ['required', 'string', Rule::unique('transaction_categories')->where(function ($query) use ($vendor) {
                    return $query->where('vendor_id', $vendor->id);
                })],
                'parent_id' => 'required|integer'
            ]);

            TransactionCategory::query()->create([
                'name' => $request['name'],
                'parent_id' => $request['parent_id'],
                'vendor_id' => $vendor->id,
            ]);

            DB::commit();
            return response()->json(['message' => 'Категория создана успешно.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Transaction category creation failed: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, TransactionCategory $category)
    {

        $vendor = $this->getActingVendor();
        $request->validate([
            'name' => ['required', 'string', Rule::unique('transaction_categories')
                ->ignore($category->id)
                ->where(function ($query) use ($vendor) {
                    return $query->where('vendor_id', $vendor->id);
                })],
            'parent_id' => [
                'required',
                'numeric'
            ],
        ]);


        $category->update([
            'name' => $request['name'],
            'parent_id' => $request['parent_id'],
            'vendor_id' => $vendor->id
        ]);

        return response()->json(['message' => 'Категория успешно обновлена']);
    }

    public function delete(TransactionCategory $category)
    {
        if ($category->children()->exists()) {
            return response()->json(['message' => 'Сначала удалите подкатегорию'],400);
        };
        $category->delete();
        return response()->json(['message' => 'Категория удалена успешно']);
    }

    public function categoryOption()
    {
        $categories =  $this->getActingVendor()->transactionCategories()->select('id', 'name')->get();
        return response()->json(['data' => $categories]);
    }

    public function getSubcategoriesByCategoryIdOption(TransactionCategory $category)
    {
        $subcategories = $category->children()->select('id', 'name')->get();


        return TransactionCategoryResource::collection($subcategories);
    }

    public function subcategories(Request $request)
    {
        $search = $request->search;
        $subcategories = $this->getActingVendor()->transactionSubcategories()
            ->when($search, fn($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->select('id', 'name', 'parent_id')
            ->with(['parent' => fn($q) => $q->select('id', 'name')])->paginate($request->per_page ?? 10);

        return TransactionSubcategoryResource::collection($subcategories);
    }
}
