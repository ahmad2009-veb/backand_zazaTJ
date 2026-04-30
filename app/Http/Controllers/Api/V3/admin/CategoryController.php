<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CategoryItemResource;
use App\Http\Resources\Admin\CategoryResource;
use App\Http\Resources\Admin\SubCategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\Warehouse;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Rap2hpoutre\FastExcel\FastExcel;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search ?? '';
        $categories = Category::query()
            ->where(['position' => 0])
            ->when($search !== '', function ($query) use ($search) {
                return $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('id', 'LIKE', '%' . $search);
            })
            ->latest()
            ->paginate($request->per_page ?? 12);
        return CategoryResource::collection($categories);
    }

    public function allCategories(Request $request)
    {
        $request->validate([
            'store_id' => 'nullable|exists:stores,id'
        ]);

        $categories = Category::query()

        ->when($request->store_id, function (Builder $q, $storeId)  {
            $q->whereHas('store', function($q) use($storeId)  {
                $q->where('id', $storeId);
            });
        })
            ->where(['position' => 0])
            ->latest()
            ->get();
        return CategoryResource::collection($categories);
    }

    public function restaurantCategories(string $id, Request $request)
    {

        if ($id == 9999) {
            $categories = Category::query()->active()->get();
            return CategoryItemResource::collection($categories);
        }
        $warehouse = Warehouse::query()->find($id);

        if ($warehouse != null) {
            return CategoryItemResource::collection($warehouse->categories());
        }
        return response()->json(['message' => 'Склад не найден'], 404);
    }

    public function restaurantSubCategories(Category $category)
    {
        return CategoryItemResource::collection($category->childes);
    }

    public function subcategories(Request $request)
    {

        $search = $request->search ?? '';
        $subCategories = Category::with(['parent'])
            ->when($search !== '', function ($query) use ($search) {
                return $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('id', 'LIKE', '%' . $search);
            })
            ->where(['position' => 1])
            ->latest()
            ->paginate($request->per_page ?? 12);
        return SubCategoryResource::collection($subCategories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'max:100', Rule::unique('categories', 'name')->where(function ($query) use ($request) {
                return $query->where('store_id', $request->store_id);
            })],
            'image' => 'required|mimes:jpg,jpeg,png,svg|max:1024',
            'parent_id' => 'required|numeric',
            'position' => 'required|numeric',
            'store_id' => 'required|integer|exists:stores,id'
        ], [
            'name.required' => trans('messages.Name is required!'),
        ]);
        $category = new Category();
        $category->name = $request->name;
        $category->image = $request->has('image') ? Helpers::upload(
            'category/',
            'png',
            $request->file('image')
        ) : 'def.png';
        $category->parent_id = $request->parent_id == null ? 0 : $request->parent_id;
        $category->position = $request->position;
        $category->store_id = $request->store_id;
        $category->save();


        return response()->json(['message' => trans('messages.category_added_successfully')], 201);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|max:100|unique:categories,name,' . $category->id,
            'image' => 'nullable|mimes:jpg,jpeg,png,svg|max:1024',
            'parent_id' => 'required|numeric',
            'position' => 'required|numeric',
            'store_id' => 'required|integer|exists:stores,id'
        ], [
            'name.required' => trans('messages.Name is required!'),
        ]);

        $category->name = $request->name;
        $category->image = $request->has('image') ? Helpers::update(
            'category/',
            $category->image,
            'png',
            $request->file('image')
        ) : $category->image;
        $category->parent_id = $request->parent_id ?? $category->parent_id;
        $category->position = $request->position ?? $category->position;
        $category->store_id = $request->store_id;
        $category->save();

        return response()->json(['message' => trans('messages.category_updated_successfully')], 200);
    }

    public function update_priority(Category $category, Request $request)
    {
        $category->priority = $request->priority ?? 0;
        $category->save();
        return response()->json(['message' => trans('messages.category_priority_updated successfully')]);
    }

    public function updateStatus(Request $request, Category $category)
    {
        $request->validate([
            'status' => 'required|numeric'
        ]);
        $category->status = $request->input('status');
        $category->save();
        return response()->json(['message' => trans('messages.category_status_updated')]);
    }

    public function updatePopular(Request $request, Category $category)
    {
        $request->validate([
            'popular' => 'required|numeric'
        ]);
        $category->is_popular = $request->input('popular');
        $category->save();
        return response()->json(['message' => 'ok']);
    }

    public function delete(Category $category)
    {
        if ($category->childes()->count() == 0) {
            $category->delete();
            return response()->json(['message' => 'Категория удалена']);
        } else {
            return response()->json(['message' => trans('messages.remove_sub_categories_first')]);
        }
    }

    public function search(Request $request)
    {
        $res = preg_replace('/\s+/', ' ', $request->input('key')); // remove spaces make single cpace
        $key = explode(' ', $res);

        $categories = Category::query()->when($request['sub_category'], function ($query) {
            $query->where('position', 1);
        })->where(function ($q) use ($key) {
            for ($i = 0; $i < count($key); $i++) {
                $q->orWhere('name', 'like', "%{$key[$i]}%");
            }
        })->limit(50)->get();

        return $categories;
    }

    public function exportCategories($type)
    {

        $collection = Category::all();

        if ($type == 'excel') {
            return (new FastExcel(Helpers::export_categories($collection)))->download('Categories.xlsx');
        } elseif ($type == 'csv') {
            return (new FastExcel(Helpers::export_categories($collection)))->download('Categories.csv');
        }
    }
}
