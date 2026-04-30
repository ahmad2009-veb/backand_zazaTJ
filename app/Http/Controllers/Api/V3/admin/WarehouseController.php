<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\warehouse\CreateProductRequest;
use App\Http\Requests\Api\v3\admin\warehouse\WarehouseStoreRequest;
use App\Http\Requests\Api\v3\admin\warehouse\WarehouseUpdateRequest;
use App\Http\Resources\Admin\Warehouse\ProductsList;
use App\Http\Resources\Admin\Warehouse\WarehouseProductResource;
use App\Http\Resources\Admin\Warehouse\WarehouseResource;
use App\Models\Arrival;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $warehouses = Warehouse::query()->when($search, function ($query, $search) {
            return $query->where('name', 'LIKE', '%' . $search . '%')->orWhere('id', 'LIKE', '%' . $search . '%');
        })->paginate($request->per_page ?? 10);
        return WarehouseResource::collection($warehouses);
    }

    public function mainWarehouses(Request $request)
    {

        $warehouses = Warehouse::query()->where('status', 1)->get();
        return WarehouseResource::collection($warehouses);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(WarehouseStoreRequest $request)
    {

        $warehouse = Warehouse::create([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'phone' => $request->input('phone'),
            'responsible' => $request->input('responsible'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ]);

        return response()->json(['data' => $warehouse], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return WarehouseResource
     */
    public function show(Warehouse $warehouse)
    {

        return WarehouseResource::make($warehouse->load('owner'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(WarehouseUpdateRequest $request, Warehouse $warehouse)
    {
        $warehouse->update([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'phone' => $request->input('phone'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'responsible' => $request->input('responsible'),
        ]);

        return response()->json(['message' => 'updated successfully!'], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->owner) {
            $warehouse->owner()->delete();
        }
        $warehouse->delete();
        return response()->json(['data' => 'Успешно удалено']);
    }

    public function toggleStatus(Request $request, Warehouse $warehouse): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'status' => 'required|in:0,1',
        ]);
        $warehouse->status = $request->status;
        $warehouse->save();
        return response()->json(['message' => 'Статус склада обновлен']);
    }

    public function exportData(Request $request)
    {

        $request->validate([
            'type' => 'required|in:all,id,date',
            'from' => 'required_if:type,id,date',
            'to' => 'required_if:type,id,date',
        ]);
        $warehouses = collect();
        switch ($request->get('type')) {
            case 'all':
                $warehouses = Warehouse::all();
                break;
            case 'id':
                $warehouses = Warehouse::query()->whereBetween('id', [$request->get('from'), $request->get('to')])->get();
                break;
            case 'date':
                $warehouses = Warehouse::query()->whereBetween('created_at', [$request->get('from'), $request->get('to')])->get();
                break;
            default:
                $warehouses = Warehouse::all();
        }

        $data = [];
        $warehouses->each(function ($warehouse) use (&$data) {

            $data[] = [
                'id' => $warehouse->id,
                'Название' => $warehouse->name,
                'Адрес' => $warehouse->address,
                'Телефон' => $warehouse->phone,
                'Широта' => $warehouse->latitude,
                'Долгота' => $warehouse->longitude,
                'Ответственный' => $warehouse->responsible,
            ];
        });

        return (new FastExcel($data))->download('warehouses.' . now()->format('Y-m-d') . '.xlsx');
    }

    public function getWarehouseProducts(Request $request)
    {
        $search = $request->get('search', '');



        $products = Product::with(['warehouse', 'variations', 'subCategory'])
            ->when($search, function ($query, $search) {
                // Optionally, search for products by name
                $query->where('name', 'LIKE', '%' . $search . '%');
            })->latest()->paginate($request->per_page ?? 10);


        return ProductsList::collection($products);
    }

    public function createProduct(CreateProductRequest $request)
    {
        if ($request['discount_type'] == 'percentage') {
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
        $product->store_id = $request->store_id;
        $product->veg = $request->veg;
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
        $product->image = Helpers::upload('product/', 'png', $request->file('image'));
        $product->available_time_starts = $request->available_time_starts;
        $product->available_time_ends = $request->available_time_ends;
        $product->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $product->discount_type = $request->discount_type;
        $product->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $product->add_ons = $request->has('addon_ids') ? json_encode($request->addon_ids) : json_encode([]);

        $product->save();

        if ($request->warehouse_id) {
            $data = [
                'quantity' => $request['warehouse_qty'],
                'wholesale_price' => $request['warehouse_wholesale_price'] ?? 0,
                'retail_price' => $request['warehouse_retail_price'] ?? 0,
                'warehouse_id' => $request['warehouse_id'],
                'status' => $request['warehouse_status'],
                'purchase_price' => $request['warehouse_purchase_price'] ?? 0,
                'product_id' => $product->id,
                'product_code' => $request['warehouse_product_code'],
            ];
            DB::table('warehouse_product')->insert($data);
            $warehouseProduct = WarehouseProduct::where(['product_id' => $product->id, 'warehouse_id' => $request['warehouse_id']])->first();
            $warehouseProduct->updateTotalPrices();
        }

        return response()->json(['message' => 'created successfully', 'data' => $product->id], 201);
    }

    public function counts()
    {
        $counts = [
            'products' => Product::all()->count(),
            'arrivals' => Arrival::all()->count(),
            'sales' => 0,
            'flow' => 0,
            'write-of' => 0
        ];

        return response()->json($counts);
    }
}
