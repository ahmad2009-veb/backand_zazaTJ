<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Warehouse\ProductsList;
use App\Http\Resources\Admin\Warehouse\WarehouseResource;
use App\Http\Resources\Admin\Warehouse\WarehouseProductResource;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Http\Traits\VendorEmployeeAccess;

class WarehouseController extends Controller
{
    use VendorEmployeeAccess;
    public function index(Request $request)
    {
        $search = $request->input('search');
        $vendor = $this->getActingVendor();

        $warehouses =   $vendor->warehouses()
            ->when($search, function ($query, $search) {
                $query->where('warehouses.name', 'LIKE', '%' . $search . '%')->orWhere('warehouses.id', 'LIKE', '%' . $search . '%');
            })->paginate($request->input('per_page', 10));

        return WarehouseResource::collection($warehouses);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'phone' => 'required',
            'responsible' => 'required',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
        ]);

        $vendor =  $this->getActingVendor();
        try {
            DB::beginTransaction();

            $warehouse = Warehouse::query()->create([
                'name' => $request->input('name'),
                'address' => $request->input('address'),
                'phone' => $request->input('phone'),
                'responsible' => $request->input('responsible'),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'store_id' => $vendor->store->id
            ]);
            DB::commit();
            return response()->json(['message' => 'Склад успешно создан'], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Что то пошло не так',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'phone' => 'required',
            'responsible' => 'required',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $warehouse->update([
                'name' => $request->input('name'),
                'address' => $request->input('address'),
                'phone' => $request->input('phone'),
                'responsible' => $request->input('responsible'),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
            ]);

            DB::commit();

            return response()->json(['message' => 'Успешно обновлен']);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Что то пошло не так',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function toggleStatus(Warehouse $wareHouse, Request $request)
    {
        $request->validate([
            'status' => 'required|in:0,1',
        ]);

        $wareHouse->status = $request->status;
        $wareHouse->save();

        return response()->json(['message' => 'Статус склада обновлен'], 201);
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->owner) {
            $warehouse->owner()->delete();
        }
        $warehouse->delete();
        return response()->json(['message' => "Успешно удалено"]);
    }

    public function show(Warehouse $warehouse)
    {
        return WarehouseResource::make($warehouse->load('owner'));
    }

    public function export(string $type)
    {
        $vendor = $this->getActingVendor();

        $warehouses = $vendor->warehouses()->select(
            ['warehouses.id', 'warehouses.name', 'warehouses.address', 'warehouses.responsible', 'warehouses.phone']
        )
            ->get()->map(function ($warehouse) {
                return [
                    'id' => $warehouse->id,
                    'название' => $warehouse->name,
                    'адресс' => $warehouse->address,
                    'ответственный' => $warehouse->responsible,
                    'телефон' => $warehouse->phone,
                ];
            });

        switch ($type) {
            case 'excel':
                return (new FastExcel($warehouses))->download('delivery-mens.xlsx');
                break;
            case 'csv':
                return (new FastExcel($warehouses))->download('delivery-mens.csv');
                break;

            default:
                return response()->json(['message' => 'укажите тип файла']);
                break;
        }
    }

    public function getProducts(Request $request)
    {

        $vendor = $this->getActingVendor();


        $search = $request->input('search');
        $products = $vendor->products()
            ->with(['variations', 'subCategory', 'warehouse'])
            ->whereDoesntHave('receiptItems', function ($query) {
                $query->whereHas('receipt', function ($query) {
                    $query->where('status', \App\Enums\ReceiptStatusEnum::PENDING->value);
                });
            })
            ->when($search, function ($query, $search) {
                // Optionally, search for products by name
                $query->where('products.name', 'LIKE', '%' . $search . '%');
            })->paginate($request->per_page ?? 10);
        return ProductsList::collection($products);
    }

    public function selectOptions()
    {
        return $this->getActingVendor()->warehouses->map(function ($el) {
            return [
                'id' => $el->id,
                'name' => $el->name
            ];
        });
    }

    public function categories(Warehouse $warehouse)
    {
        $categories =  $warehouse->categories()->map(fn($el) => ['id' => $el?->id, 'name' => $el?->name]);

        return response()->json(['data' => $categories], 200);
    }

    public function getWarehouseProducts(Warehouse $warehouse, Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 16);

        // Get product IDs from warehouse_product table
        $warehouseProductIds = $warehouse->warehouseProducts()->pluck('product_id')->toArray();

        // Get products from BOTH sources:
        // 1. Products with direct warehouse_id (excluding those already in warehouse_product)
        // 2. Products from warehouse_product table
        $directProducts = Product::where('warehouse_id', $warehouse->id)
            ->whereNotIn('id', $warehouseProductIds)
            ->when($search, function ($query) use ($search) {
                return $query->where('name', 'like', '%' . $search . '%');
            })
            ->with(['variations', 'subCategory'])
            ->get();

        $warehouseProducts = $warehouse->warehouseProducts()
            ->with(['product.variations', 'product.subCategory'])
            ->whereHas('product', function ($query) use ($search) {
                if ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                }
            })
            ->get();

        // Merge both collections and paginate manually
        $merged = collect();

        // Add direct products
        foreach ($directProducts as $product) {
            $merged->push($product);
        }

        // Add warehouse_product entries
        foreach ($warehouseProducts as $wp) {
            $merged->push($wp);
        }

        // Manual pagination
        $page = $request->input('page', 1);
        $total = $merged->count();
        $items = $merged->forPage($page, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return WarehouseProductResource::collection($paginator);
    }

    /**
     * Get stock quantities for specific products/variations in a warehouse.
     * Used for validating maximum transferable quantities.
     */
    public function stockLookup(Request $request, Warehouse $warehouse)
    {
        $vendor = $this->getActingVendor();

        // Validate warehouse belongs to vendor
        if (!$vendor->warehouses()->where('warehouses.id', $warehouse->id)->exists()) {
            return response()->json([
                'message' => 'Склад не найден или не принадлежит вам'
            ], 403);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.variation_id' => 'nullable',
        ]);

        $items = $validated['items'];

        $result = [];
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $variationId = $item['variation_id'] ?? null;

            $quantity = 0;
            if ($variationId) {
                $variation = \App\Models\ProductVariation::where('variation_id', $variationId)->first();
                $quantity = $variation ? (float) $variation->quantity : 0;
            }

            $responseItem = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];

            if ($variationId !== null) {
                $responseItem['variation_id'] = $variationId;
            }

            $result[] = $responseItem;
        }

        return response()->json(['data' => $result]);
    }
}
