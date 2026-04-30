<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionCategoryResource;
use App\Models\Category;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionCategoryController extends Controller
{

    public function categories(Request $request)
    {
        $search = $request->search;
        $categories = auth()->user()->transactionCategories()->select('id', 'name', 'admin_id', 'parent_id')
            ->when($search, fn($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->paginate($request->input('per_page', 10));
        return TransactionCategoryResource::collection($categories);
    }

    public function subcategories(Request $request)
    {
        $search = $request->search;
        $subcategories = auth()->user()->transactionSubcategories()
            ->when($search, fn($q) => $q->where('name', 'like', '%' . $search . '%'))

            ->select('id', 'name', 'parent_id')
            ->with(['parent' => function ($q) {
                $q->select('id', 'name');
            }])
            ->paginate($request->input('per_page', 10));
        return TransactionCategoryResource::collection($subcategories);
    }
    public function categoryOption()
    {
        $categories =  auth()->user()->transactionCategories()->select('id', 'name')->get();
        return response()->json(['data' => $categories]);
    }
    public function store(Request $request)
    {
        $admin = auth()->user();
        $request->validate([
            'name' => ['required', 'string', Rule::unique('transaction_categories')->where(function ($query) use ($admin) {
                return $query->where('admin_id', $admin->id);
            })],
            'parent_id' => 'required|numeric'
        ]);

        TransactionCategory::query()->create([
            'name' => $request['name'],
            'parent_id' => $request['parent_id'],
            'admin_id' => $admin->id,
        ]);

        return response()->json(['message' => 'Категория создана успешно.']);
    }

    public function getSubcategoriesByCategoryIdOption(TransactionCategory $category)
    {
        $subcategories = $category->children()->select('id', 'name')->get();
        return TransactionCategoryResource::collection($subcategories);
    }

    public function update(Request $request, TransactionCategory $category)
    {

        $request->validate([
            'name' => ['required', 'string', Rule::unique('transaction_categories', 'name')
                ->where('admin_id', auth()->user()->id)
                ->ignore($category->id)],
            'parent_id' => [
                'required',
                'integer',


            ],
        ]);

        $category->update([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
        ]);
        return response()->json(['message' => 'success']);
    }

    public function delete(TransactionCategory $category)
    {

        if ($category->children()->exists()) {
            return response()->json(['message' => 'Сначала удалите подкатегорию'] ,404);
        };
        $category->delete();

        return response()->json(['message' => 'success']);
    }
}
