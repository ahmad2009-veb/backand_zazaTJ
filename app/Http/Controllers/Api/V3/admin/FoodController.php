<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\FoodUpdateRequest;
use App\Http\Requests\Api\v3\FoodCreateRequest;
use App\Http\Resources\Admin\ProductAdminResource;
use App\Http\Resources\Admin\ProductShowResource;
use App\Http\Resources\ProductItemResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Food;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Store;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use App\Scopes\RestaurantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class FoodController extends Controller
{
    public function index(Request $request, string $id)
    {

        $search = $request->search;

        $category_ids = $request->category_ids;
        //        $foods = $restaurant->foods()
        //            ->when($search, function ($query, $search) {
        //                return $query->where('name', 'like', "%$search%");
        //            })
        //            ->when(!empty($category_ids), function ($query) use ($category_ids) {
        //                return $query->whereIn('category_id', $category_ids);
        //            })
        //            ->paginate($request->per_page ?? 12);
        //        return FoodItemResource::collection($foods);

        $warehouse = Warehouse::find($id);
        if ($warehouse) {
            $products = $warehouse->products()->when($search, function ($query) use ($search) {
                return $query->where('name', 'like', '%' . $search . '%');
            })
                ->when($category_ids, function ($query) use ($category_ids) {
                    return $query->whereIn('category_id', $category_ids);
                })->paginate($request->per_page ?? 12);

            return ProductItemResource::collection($products);
        }
        //        } elseif ($id == 9999) {
        //            $products = Product::query()->active()->when($search, function ($query) use ($search) {
        //                return $query->where('name', 'like', '%' . $search . '%');
        //            })
        //                ->when($category_ids, function ($query) use ($category_ids) {
        //                    return $query->whereIn('category_id', $category_ids);
        //                })->paginate($request->per_page ?? 12);
        //            return ProductItemResource::collection($products);
        //        }
        return response()->json(['message' => 'Not Found!'], 404);
    }

    public function getFoodsByIds(Request $request)
    {
        //        $food_ids = $request->food_ids ? explode(',', $request->food_ids) : [];
        //        $foods = Food::query()
        //            ->when(!empty($food_ids), function ($query) use ($food_ids) {
        //                return $query->whereIn('id', $food_ids);
        //            })
        //            ->get();
        $food_ids = $request->food_ids ? explode(',', $request->food_ids) : [];
        $products = Product::query()
            ->when(!empty($food_ids), function ($query) use ($food_ids) {
                return $query->whereIn('id', $food_ids);
            })
            ->get();
        return ProductItemResource::collection($products);
    }

    public function getFoodsByNames(Restaurant $restaurant, Request $request)
    {
        $food_names = $request->food_names ?? [];
        if (count($food_names) === 0) {
            return response()->json(['message' => 'food_names is required'], 400);
        }
        $foods = $restaurant->foods()
            ->when(!empty($food_names), function ($query) use ($food_names) {
                return $query->whereIn('name', $food_names);
            })
            ->get();
        return ProductItemResource::collection($foods);
    }

    public function getFoods(Request $request)
    {
        $keyword = $request->input('search', '');
        $key = explode(' ', $request['search']);
        $products = Product::with(['category', 'category.childes'])
            ->when($keyword, function ($query) use ($key) {
                foreach ($key as $value) {
                    $query->where('name', 'like', "%{$value}%");
                }
            })->paginate($request->per_page);


        return ProductAdminResource::collection($products);
    }

    public function show(Product $product)
    {
        // Load variations relationship
        $product->load('variations');
        return ProductShowResource::make($product);
    }

    public function delete(Product $product)
    {
        if ($product->image) {
            if (Storage::disk('public')->exists('product/' . $product['image'])) {
                Storage::disk('public')->delete('product/' . $product['image']);
            }
        }
        $product->delete();

        return response()->json(['message' => trans('messages.product_deleted_successfully')]);
    }

    public function store(FoodCreateRequest $request)
    {


        if ($request['discount_type'] == 'percent') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['price'] <= $dis) {
            return response()->json(['message' => trans('messages.discount_can_not_be_more_than_or_equal')]);
        }

        $product = new Product;
        $product->name = $request->name;

        $category = [];
        if ($request->category_id != null) {
            $category[] = [
                'id' => $request->category_id,
                'position' => 1,
            ];
        }
        if ($request->sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_category_id,
                'position' => 2,
            ];
        }
        if ($request->sub_sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ];
        }
        $product->category_ids = json_encode($category);
        $product->category_id = $request->sub_category_id ? $request->sub_category_id : $request->category_id;
        $product->description = $request->description;
        //        $product->veg = $request->veg;
        $product->discount = $request->discount;
        $product->product_code = $request->product_code;
        $product->warehouse_id = $request->warehouse_id;
        $choice_options = [];

        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_' . $no;
                if ($request[$str][0] == null) {
                    return response()->json(['message' => trans('messages.attribute_choice_option_value_can_not_be_null')]);
                }
                $item['name'] = 'choice_' . $no;
                $item['title'] = $request->choice[$key];
                $item['options'] = explode(',', implode('|', preg_replace('/\s+/', '', $request[$str])));
                $choice_options[] = $item;
            }
        }

        //        return $request->choice;
        $product->choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);
        $options = [];
        if ($request->has('choice_no')) {
            $choices = $request->get('choice_no');
            sort($choices);
            foreach ($choices as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('|', $request[$name]);

                array_push($options, explode(',', $my_str));
            }
        }
        $variations = [];
        //Generates the combinations of customer choice options
        $combinations = Helpers::combinations($options);

        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $item) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $item);
                    } else {
                        $str .= str_replace(' ', '', $item);
                    }
                }
                $item = [];
                $item['type'] = $str;
                $item['price'] = abs($request['price_' . str_replace('.', '_', $str)]);
                $variations[] = $item;
            }
        }
        $product->variations = json_encode($variations);
        $product->price = $request->price;
        $product->quantity = $request->quantity;
        $product->purchase_price = $request->purchase_price;
        $product->image = Helpers::upload('product/', 'png', $request->file('image'));
        $product->available_time_starts = $request->available_time_starts;
        $product->available_time_ends = $request->available_time_ends;
        $product->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $product->discount_type = $request->discount_type;
        $product->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $product->add_ons = $request->has('addon_ids') ? json_encode($request->addon_ids) : json_encode([]);

        $product->save();
        //        if ($request->store_id) {
        //            $store = Store::find($request->store_id);
        //            $store->products()->attach($product->id);
        //        }
        //        if ($request->warehouse_id) {
        //            $data = [
        //                'quantity' => $request['warehouse_qty'],
        //                'wholesale_price' => $request['warehouse_wholesale_price'] ?? 0,
        //                'retail_price' => $request['warehouse_retail_price'] ?? 0,
        //                'warehouse_id' => $request['warehouse_id'],
        ////                'status' => $request['warehouse_status'],
        //                'purchase_price' => $request['warehouse_purchase_price'] ?? 0,
        //                'product_id' => $product->id,
        //                'product_code' => $request['warehouse_product_code'],
        //
        //            ];
        //            DB::table('warehouse_product')->insert($data);
        //            $warehouseProduct = WarehouseProduct::where(['product_id' => $product->id, 'warehouse_id' => $request['warehouse_id']])->first();
        //            $warehouseProduct->updateTotalPrices();
        //        }


        return response()->json(['message' => 'created successfully', 'data' => $product['id']], 201);
    }

    public function update(FoodUpdateRequest $request, Product $product)
    {

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['price'] <= $dis) {
            return response()->json(['message' => trans('messages.discount_can_not_be_more_than_or_equal')]);
        }
        $product->name = $request->name;

        // Update category IDs
        $category = [];
        if ($request->category_id != null) {
            $category[] = [
                'id' => $request->category_id,
                'position' => 1,
            ];
        }
        if ($request->sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_category_id,
                'position' => 2,
            ];
        }
        if ($request->sub_sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ];
        }

        $product->category_ids = json_encode($category);
        $product->category_id = $request->sub_category_id ? $request->sub_category_id : $request->category_id;
        $product->description = $request->description;

        $product->discount = $request->discount;
        $product->product_code = $request->product_code;
        $choice_options = [];
        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_' . $no;
                if ($request[$str][0] == null) {
                    return response()->json(['message' => trans('messages.attribute_choice_option_value_can_not_be_null')]);
                }
                $item['name'] = 'choice_' . $no;
                $item['title'] = $request->choice[$key];
                $item['options'] = explode(',', implode('|', preg_replace('/\s+/', '', $request[$str])));
                $choice_options[] = $item;
            }
        }

        $product->choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);
        $options = [];
        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('|', $request[$name]);
                array_push($options, explode(',', $my_str));
            }
        }

        $variations = [];
        $combinations = Helpers::combinations($options);
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $item) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $item);
                    } else {
                        $str .= str_replace(' ', '', $item);
                    }
                }
                $item = [];
                $item['type'] = $str;
                $item['price'] = abs($request['price_' . str_replace('.', '_', $str)]);
                $variations[] = $item;
            }
        }
        $product->variations = json_encode($variations);

        $product->price = $request->price;
        $product->quantity = $request->quantity;
        $product->purchase_price = $request->purchase_price ?? $product->purchase_price;
        $product->quantity = $requst->quantitiy ?? $product->quantity;
        if ($request->hasFile('image')) {
            $product->image = Helpers::upload('product/', 'png', $request->file('image'));
        }
        $product->available_time_starts = $request->available_time_starts;
        $product->available_time_ends = $request->available_time_ends;
        $product->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $product->discount_type = $request->discount_type;
        $product->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $product->add_ons = $request->has('addon_ids') ? json_encode($request->addon_ids) : json_encode([]);

        $product->save();

        return response()->json(['message' => 'Updated successfully'], 200);
    }

    public function updateStatus(Request $request, Product $product)
    {
        $request->validate([
            'status' => 'required|boolean',
        ]);
        $product->status = $request->status;
        $product->save();
        return response()->json(['message' => 'status updated successfully']);
    }

    public function bulkImportData(Request $request)
    {
        // Validate the request
        $request->validate([
            'products_file' => 'required|file|mimes:xlsx,csv',
        ]);

        try {
            // Import the file using FastExcel
            $collections = (new FastExcel)->import($request->file('products_file'));
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('messages.you_have_uploaded_a_wrong_format_file')
            ], 400);
        }

        $data = [];
        foreach ($collections as $collection) {
            if ($collection['name'] == "" || $collection['category_id'] == "" || $collection['sub_category_id'] === "" || $collection['price'] === "" || $collection['available_time_starts'] == "" || $collection['available_time_ends'] == "" || $collection['restaurant_id'] == "") {
                return response()->json([
                    'message' => trans('messages.please_fill_all_required_fields')
                ], 400);
            }

            $data[] = [
                'name' => $collection['name'],
                'category_id' => $collection['sub_category_id'] ?: $collection['category_id'],
                'category_ids' => json_encode([
                    ['id' => $collection['category_id'], 'position' => 0],
                    ['id' => $collection['sub_category_id'], 'position' => 1],
                ]),
                'veg' => $collection['veg'] ?? 0,
                'price' => $collection['price'],
                'discount' => $collection['discount'],
                'discount_type' => $collection['discount_type'],
                'description' => $collection['description'],
                'available_time_starts' => $collection['available_time_starts'],
                'available_time_ends' => $collection['available_time_ends'],
                'store_id' => $collection['store_id'],
                'add_ons' => json_encode([]),
                'attributes' => json_encode([]),
                'choice_options' => json_encode([]),
                'variations' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        try {
            DB::beginTransaction();
            DB::table('food')->insert($data);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => trans('messages.failed_to_import_data'),
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => trans('messages.product_imported_successfully', ['count' => count($data)]),
            'data' => $data
        ], 200);
    }

    public function bulkExportData(Request $request)
    {
        $request->validate([
            'type' => 'required|in:id,date,all',
            'from_date' => 'required_if:type,date|date_format:Y-m-d',
            'to_date' => 'required_if:type,date|date_format:Y-m-d',
            'from_id' => 'required_if:type,id',
            'to_id' => 'required_if:type,id',
        ]);
        $type = $request->get('type');
        $from_id = $request->get('from_id');
        $to_id = $request->get('to_id');
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $products = Product::query()->when($type == 'id', function ($query) use ($from_id, $to_id) {
            $query->whereBetween('id', [$from_id, $to_id]);
        })->when($type == 'date', function ($query) use ($from_date, $to_date) {
            $query->whereBetween('created_at', [$from_date, $to_date]);
        })->get();

        return (new FastExcel(ProductLogic::format_export_foods($products)))->download('Products.' . $type);
    }

    public function getTemplate()
    {
        $filePath = 'template/product_import_template.xlsx';
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->download($filePath);
        } else {
            // Return a 404 response if the file does not exist
            abort(404, 'File not found.');
        }
    }
}
