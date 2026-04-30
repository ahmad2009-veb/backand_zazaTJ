<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\arrival\ArrivalStoreRequest;
use App\Http\Requests\Api\v3\admin\arrival\ArrivalUpdateRequest;
use App\Http\Resources\Admin\Arrivals\ArrivalResource;
use App\Http\Resources\Admin\Arrivals\ArrivalsListResource;
use App\Models\Arrival;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use App\Services\ArrivalService;
use App\Services\ProductService;
use Illuminate\Http\Request;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Rap2hpoutre\FastExcel\FastExcel;

class ArrivalController extends Controller
{
    public function __construct(public ArrivalService $arrivalService) {}
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->per_page ?? 10;
        $page  = $request->input('page');
        $vendor = auth()->user();
        // return  $vendor->warehouses()->with('arrivals')->get();
        $arrivalsQuery = auth()->user()->warehouses()
            ->with(['arrivals' => function ($query) use ($search) {
                $query->when($search, function ($query, $search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('company_name', 'like', '%' . $search . '%');
                });
            }])
            ->get()
            ->pluck('arrivals')
            ->flatten();

        return  ArrivalsListResource::collection($this->customPaginate($arrivalsQuery, $perPage, $page));
    }




    function customPaginate($collection, $perPage = 10, $currentPage = 1)
    {
        $currentPage = $currentPage < 1 ? 1 : $currentPage; // Ensure valid page number
        $total = $collection->count(); // Get total items count

        $offset = ($currentPage - 1) * $perPage; // Calculate offset
        $items = $collection->slice($offset, $perPage); // Get items for the current page

        return new LengthAwarePaginator(
            $items,                // Items for current page
            $total,                // Total items count
            $perPage,              // Items per page
            $currentPage,          // Current page
            ['path' => request()->url(), 'query' => request()->query()] // For maintaining pagination links
        );
    }


    public function store(ArrivalStoreRequest $request)
    {

        $arrival = Arrival::create([
            'name' => $request['name'],
            'provider_phone' => $request['provider_phone'],
            'provider_name' => $request['provider_name'],
            'company_name' => $request['company_name'],
            'address' => $request['address'],
            'identification_info' => $request['identification_info'],
            'warehouse_id' => $request['warehouse_id'],
            'provider_contact' => $request['provider_contact'],
            'status' => $request['status'],
        ]);

        $products = $request['products'];

        foreach ($products as $key => $value) {

            $warehouse_product  = new WarehouseProduct();
            $warehouse_product->product_id = $value['product_id'];
            $warehouse_product->quantity = $value['quantity'];
            $warehouse_product->purchase_price = $value['purchase_price'];
            $warehouse_product->product_code = $value['product_code'];
            $warehouse_product->retail_price = $value['retail_price'];
            $warehouse_product->status = $arrival->status;
            $warehouse_product->warehouse_id = $arrival->warehouse_id;

            if ($request->hasFile('products.' . $key . '.image')) {
                $imagePath = $request->file('products.' . $key . '.image')->store('warehouse_products', 'public');
                $warehouse_product->image = $imagePath;
            }

            $productCodeSame =   $this->arrivalService->checkProductCodeSame($value['product_id'], $value['product_code']);
            if (!$productCodeSame) {
                $newCopiedProduct =    $this->arrivalService->createNewProductArrival($value);
                $warehouse_product->product_id = $newCopiedProduct->id;
            } else {
                $product = Product::query()->find($value['product_id']);
                $product->quantity += $value['quantity'];
                $product->price = $value['retail_price'];
                $product->purchase_price = $value['purchase_price'];
                $product->save();
            }

            $warehouse_product->save();
            // $warehouseProduct = WarehouseProduct::create($product);
            $arrival->warehouseProducts()->attach($warehouse_product->id);
        }
        if ($request->has('file')) {

            $data =  (new FastExcel())->import($request->file('file'));
            //удаление скобок  с приходящего файла
            $processedData =  (new ProductService)->removeBrackets($data);

            //Валидация входных данных/
            $validator = Validator::make($processedData->toArray(), (new ProductService)->warehouseProductRules);
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            //Добавление поля статус от $arrival
            $processedData = $processedData->map(function ($item) use ($arrival) {
                $item['status'] = $arrival->status;
                $item['warehouse_id'] = $arrival->warehouse_id;
                return $item;
            });
            $warehouseProducts = $processedData->map(function ($item) {

                return WarehouseProduct::create($item->toArray());
            });


            $arrival->warehouseProducts()->attach($warehouseProducts->pluck('id')->toArray());
        }

        return response()->json(['message' => 'Приход успешно создан']);
    }

    public function update(ArrivalUpdateRequest $request, Arrival $arrival)
    {

        $arrival->update([
            'name' => $request['name'],
            'provider_phone' => $request['provider_phone'],
            'provider_name' => $request['provider_name'],
            'company_name' => $request['company_name'],
            'address' => $request['address'],
            'identification_info' => $request['identification_info'],
            'warehouse_id' => $request['warehouse_id'],
            'provider_contact' => $request['provider_contact'],
            'status' => $request['status'],
        ]);

        $products = $request['products'];
        $arrival->warehouseProducts()->each(function (WarehouseProduct $warehouseProduct, $key) use ($products, $arrival, $request) {
            $productData = collect($products)->firstWhere('id', $warehouseProduct->id);


            if ($productData) {
                $warehouseProduct->update([
                    'status' => $arrival['status'],
                    'warehouse_id' => $arrival['warehouse_id'],
                    'purchase_price' => $productData['purchase_price'],
                    'quantity' => $productData['quantity'],
                    'product_code' => $productData['product_code'],
                    'retail_price' => $productData['retail_price'],
                    'product_id' => $productData['product_id'],
                ]);



                if ($request->hasFile('products.' . $key . '.image')) {
                    Storage::delete('public/' . $warehouseProduct->image);
                    $path = $request->file('products.' . $key . '.image')->store('warehouse_products', 'public');
                    $warehouseProduct->image = $path;
                    $warehouseProduct->save();
                }
            }
        });

        // Обрабатываем продукты без `id`, создавая новые записи
        foreach ($products as $key  => $productData) {
            if (empty($productData['id'])) {
                $warehouseProduct = $arrival->warehouseProducts()->create([
                    'status' => $arrival['status'],
                    'warehouse_id' => $arrival['warehouse_id'],
                    'purchase_price' => $productData['purchase_price'],
                    'quantity' => $productData['quantity'],
                    'product_code' => $productData['product_code'],
                    'retail_price' => $productData['retail_price'],
                    'product_id' => $productData['product_id'],
                ]);

                if ($request->hasFile('products.' . $key . '.image')) {
                    $path = $request->file('products.' . $key . '.image')->store('warehouse_products', 'public');
                    $warehouseProduct->image  = $path;
                    $warehouseProduct->save();
                }
                $productCodeSame =   $this->arrivalService->checkProductCodeSame($productData['product_id'], $productData['product_code']);
                if (!$productCodeSame) {
                    $newCopiedProduct =    $this->arrivalService->createNewProductArrival($productData);
                    $warehouseProduct->product_id = $newCopiedProduct->id;
                } else {
                    $product = Product::query()->find($productData['product_id']);
                    $product->quantity += $productData['quantity'];
                    $product->price = $productData['retail_price'];
                    $product->purchase_price = $productData['purchase_price'];
                    $product->save();
                }

                $arrival->arrivalWarehouseProducts()->create(['arrival_id' => $arrival->id, 'warehouse_product_id' => $warehouseProduct->id]);
            }
        }
        return response()->json(['message' => 'Успешно обновлено']);
    }

    public function show(Arrival $arrival)
    {
        return ArrivalResource::make($arrival->load('warehouseProducts'));
    }

    public function getTemplate()
    {
        $filePath = 'template/arrival_product_template.xlsx';
        if (Storage::disk('public')->exists($filePath)) {
            // Provide a download response
            return Storage::disk('public')->download($filePath);
        }
        return response()->json(['error' => 'Файл не найден.'], 404);
    }

    public function delete(Arrival $arrival)
    {   $arrival?->warehouseProducts()->delete();
        $arrival?->arrivalWarehouseProducts()->delete();
        $arrival->delete();
        return response()->json(['message' => 'Удалено успешно']);
    }
}
