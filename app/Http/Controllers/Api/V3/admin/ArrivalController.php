<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\arrival\ArrivalStoreRequest;
use App\Http\Requests\Api\v3\admin\arrival\ArrivalUpdateRequest;
use App\Http\Resources\Admin\Arrivals\ArrivalResource;
use App\Http\Resources\Admin\Arrivals\ArrivalsListResource;
use App\Models\Arrival;
use App\Models\Product;
use App\Models\WarehouseProduct;
use App\Services\ArrivalService;
use Illuminate\Http\Request;

class ArrivalController extends Controller
{
    public function __construct(public ArrivalService $arrivalService) {}

    public function index(Request $request)
    {
        $search = $request->get('search');

        $arrivals = Arrival::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('company_name', 'like', '%' . $search . '%')
                        ->orWhereHas('warehouse', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                });
            })->latest()->paginate($request->per_page ?? 10);

        return ArrivalsListResource::collection($arrivals);
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
        foreach ($products as $product) {

            $productData = $product;
            $productData['status'] = $arrival->status;
            $productData['warehouse_id'] = $arrival->warehouse_id;

            $productCodeSame =   $this->arrivalService->checkProductCodeSame($productData['product_id'], $productData['product_code']);

            if (!$productCodeSame) {
                $newCopiedProduct =    $this->arrivalService->createNewProductArrival($productData);
                $productData['product_id'] = $newCopiedProduct->id;
            } else {
                $product  = Product::query()->find($productData['product_id']);
                $product->quantity += $productData['quantity'];
                $product->price = $productData['retail_price'];
                $product->purchase_price = $productData['purchase_price'];
                $product->save();
            }
            $warehouseProduct = WarehouseProduct::create($productData);
            $arrival->arrivalWarehouseProducts()->create(['arrival_id' => $arrival->id, 'warehouse_product_id' => $warehouseProduct->id]);
        }

        return response()->json(['message' => 'Приход успешно создан']);
    }

    public function show(Arrival $arrival)
    {

        return ArrivalResource::make($arrival->load('warehouseProducts'));
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
        $arrival->warehouseProducts()->each(function (WarehouseProduct $warehouseProduct) use ($products, $arrival) {
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
            }
        });

        foreach ($products as $productData) {
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

                $productCodeSame =   $this->arrivalService->checkProductCodeSame($productData['product_id'], $productData['product_code']);

                if (!$productCodeSame) {
                    $newCopiedProduct =    $this->arrivalService->createNewProductArrival($productData);
                    $productData['product_id'] = $newCopiedProduct->id;
                } else {
                    $product  = Product::query()->find($productData['product_id']);
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

    public function getProducts(Request $request, Arrival $arrival)
    {
        $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
        ]);
        $search = $request->get('search', '');
        $warehouseId = $request->get('warehouse_id');
        $productId = $request->get('product_id');

        $products = Product::where('warehouse_id', $warehouseId)
            ->when($search, function ($query, $search) {
                // Optionally, search for products by name
                $query->where('name', 'LIKE', '%' . $search . '%');
            })
            ->when($productId, function ($query, $productId) {
                $query->where('id', $productId);
            })

            ->get()->map(function (Product $product) {
                return [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                ];
            });


        return $products;
    }
}
