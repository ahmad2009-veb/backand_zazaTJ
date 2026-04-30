<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\CategoryResource;
use App\Http\Resources\Vendor\SubcategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Http\Traits\VendorEmployeeAccess;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    use VendorEmployeeAccess;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $categories =  $this->getActingVendor()->categories()
            ->when($search, function ($query) use ($search) {
                $query->where('categories.name', 'like', '%' . $search . '%')
                    ->orWhere('categories.id', 'like', '%' . $search);
            })
            ->where('position', 0)
            ->where('parent_id', 0)
            ->latest()
            ->paginate($request->input('per_page', 12));


        return CategoryResource::collection($categories);
    }

    public function allCategories()
    {
        $categories =    $this->getActingVendor()->categories()->active()
            ->where('parent_id', 0)
          
            ->latest()
            ->get()
            ->map(function ($el) {
                return ['id' => $el->id, 'name' => $el->name];
            });
        return response()->json(['data' => $categories]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $storeId = $this->getActingVendor()?->store->id;

            $request->validate([
                'name' => ['required', 'max:100', Rule::unique('categories', 'name')->where(function ($query) use ($storeId) {
                    return $query->where('store_id', $storeId);
                })],
                // 'image' => 'required|mimes:jpg,jpeg,png,svg,webp|max:1024',
                'parent_id' => 'required|numeric',
                'position' => 'required|numeric',
            ], [
                'name.required' => trans('messages.Name is required!'),
            ]);

            $store_id = $this->getActingVendor()?->store?->id;
            $category = new Category();
            $category->name = $request->name;
            $category->image = $request->has('image') ? Helpers::upload(
                'category/',
                'png',
                $request->file('image')
            ) : (file_exists(public_path('images/category/def.png')) ? 'category/def.png' : null);
            $category->parent_id = $request->parent_id == null ? 0 : $request->parent_id;
            $category->position = $request->position;
            $category->store_id = $store_id;
            $category->save();

            DB::commit();
            return response()->json(['message' => trans('messages.category_added_successfully')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Category creation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Category $category)
    {
        return $category;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Category $category)
    {

        $request->validate([
            'name' => [
                'required',
                'max:100',
                Rule::unique('categories')->ignore($category->id)->where('store_id', $category->store_id),
            ],
            'image' => 'nullable|mimes:jpg,jpeg,png,svg,webp|max:1024',
            'parent_id' => 'required|numeric',
            'position' => 'required|numeric',
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
        $category->save();


        return response()->json(['message' => trans('messages.category_updated_successfully')], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Category $category)
    {

        if ($category->childes()->count() == 0) {
            Storage::disk('public')->delete('/category/' . $category->image);
            $category->delete();
            return response()->json(['message' => 'Категория удалена']);
        } else {
            return response()->json(['message' => trans('messages.remove_sub_categories_first')]);
        }
    }

    public function updatePriority(Request $request, Category $category)
    {
        $request->validate(['priority' => 'required|numeric|in:0,1,2']);
        $category->priority = $request->input('priority', 0);
        $category->save();
        return response()->json(['message' => trans('messages.category_priority_updated successfully')]);
    }

    public function updateStatus(Request $request, Category $category)
    {
        $request->validate(['status' => 'required|boolean']);
        $category->status = $request->input('status');
        $category->save();
        return response()->json(['message' => trans('messages.category_status_updated')]);
    }

    public function subcategories(Request $request)
    {
        $search = $request->input('search');

        $store = $this->getActingVendor()->store;
        if (! $store) {
            return response()->json(['message' => 'Магазин не найден'], 404);
        }
    
        $query = Category::query()
            ->select(['id','name','parent_id','image','status'])
            ->with('parent')
            ->where('store_id', $store->id)
            ->where('parent_id', '!=', 0);
    

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
    
        $subcategories = $query
            ->orderBy('position', 'asc')
            ->paginate($request->input('per_page', 12));
    
        return SubcategoryResource::collection($subcategories);
    }

    public function getSubcategoriesByCategoryId(Category $category) {
        return  SubcategoryResource::collection($category->childes()->active()->get());
    }
}
