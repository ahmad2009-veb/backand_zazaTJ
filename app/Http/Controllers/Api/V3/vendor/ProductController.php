<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Enums\ReceiptStatusEnum;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Storage;

use App\Services\InventoryService;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Http\Resources\Admin\ProductShowResource;
use App\Http\Resources\Admin\Warehouse\ProductsList;
use App\Http\Requests\Api\v3\Vendor\ProductStoreRequest;
use App\Http\Requests\Api\v3\Vendor\ProductUpdateRequest;
use App\Http\Traits\VendorEmployeeAccess;



class ProductController extends Controller
{
    use VendorEmployeeAccess;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $vendor =  $this->getActingVendor();
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');

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
            })
            ->when($warehouseId, function ($query, $warehouseId) {
                // Filter by warehouse_id - check both direct warehouse_id AND warehouse_product table
                $query->where(function ($q) use ($warehouseId) {
                    $q->where('products.warehouse_id', $warehouseId)
                      ->orWhereHas('warehouseProducts', function ($wpQuery) use ($warehouseId) {
                          $wpQuery->where('warehouse_id', $warehouseId);
                      });
                });
            })
            ->paginate($request->per_page ?? 10);
        return ProductsList::collection($products);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ProductStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            $vendor = $this->getActingVendor();
            if ($request['discount_type'] == 'percent') {
                $dis = ($request['price'] / 100) * $request['discount'];
            } else {
                $dis = $request['discount'];
            }

            if ($request['price'] <= $dis) {
                return response()->json(['message' => trans('messages.discount_can_not_be_more_than_or_equal')]);
            }

        $product = new Product();
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
        $product->category_id =  $request->category_id;
        $product->description = $request->description;
        $product->store_id = $vendor->store->id;
        // $product->veg = $request->veg;
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
        // Handle new variation structure
        $variations = [];
        if ($request->has('variation_details') && is_array($request->variation_details)) {
            // New variation-based structure
            $variations = $request->variation_details;
        } else {
            // Legacy choice options structure
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
        }

        $product->variations = json_encode($variations);
        $product->variation_name = $request->input('variation_name');
        $product->price = $request->price;
        $product->purchase_price = $request->purchase_price;
        $product->quantity = $request->quantity;
        $product->image = Helpers::upload('product/', 'png', $request->file('image'));
        $product->available_time_starts = $request->available_time_starts;
        $product->available_time_ends = $request->available_time_ends;
        $product->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $product->discount_type = $request->discount_type;
        $product->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $product->add_ons = $request->has('addon_ids') ? json_encode($request->addon_ids) : json_encode([]);
        $product->warehouse_id = $request->input('warehouse_id');

        $product->save();

        // Create ProductVariation records if variation_details provided
        if ($request->has('variation_details') && is_array($request->variation_details)) {
            foreach ($request->variation_details as $detail) {
                ProductVariation::create([
                    'product_id' => $product->id,
                    'variation_id' => $detail['variation_id'] ?? null,
                    'attribute_value' => $detail['attribute_value'] ?? null,
                    'attribute_id' => $detail['attribute_id'] ?? null,
                    'cost_price' => $detail['cost_price'] ?? $request->purchase_price,
                    'sale_price' => $detail['sale_price'] ?? $request->price,
                    'quantity' => $detail['quantity'] ?? 0,
                    'barcode' => $detail['barcode'] ?? null,
                ]);
            }
        }

        // Create receipt if counterparty_id is provided
        if ($request->has('counterparty_id') && $request->counterparty_id) {
            $warehouse_id = $request->warehouse_id ?? $vendor->warehouses()->first()?->id;

            if (!$warehouse_id) {
                DB::rollBack();
                return response()->json(['message' => 'Warehouse is required to create a receipt'], 400);
            }

            // Generate receipt number
            $receiptNumber = 'RCP-' . date('YmdHis') . '-' . $product->id;

            // Calculate total amount
            $totalAmount = $request->purchase_price * $request->quantity;

            // Create receipt
            $receipt = Receipt::create([
                'vendor_id' => $vendor->id,
                'warehouse_id' => $warehouse_id,
                'counterparty_id' => $request->counterparty_id,
                'receipt_number' => $receiptNumber,
                'name' => "Receipt for product: {$product->name}",
                'status' => ReceiptStatusEnum::PENDING,
                'total_amount' => $totalAmount,
                'notes' => "Receipt for product: {$product->name}",
            ]);

            // Create receipt item
            ReceiptItem::create([
                'receipt_id' => $receipt->id,
                'product_id' => $product->id,
                'product_variation_id' => null,
                'quantity' => $request->quantity,
                'unit_price' => $request->purchase_price,
                'total_price' => $totalAmount,
                'notes' => null,
            ]);
        }

        // if ($request->warehouse_id) {
        //     $data = [
        //         'quantity' => $request['warehouse_qty'],
        //         'wholesale_price' => $request['warehouse_wholesale_price'] ?? 0,
        //         'retail_price' => $request['warehouse_retail_price'] ?? 0,
        //         'warehouse_id' => $request['warehouse_id'],
        //         'status' => $request['warehouse_status'],
        //         'purchase_price' => $request['warehouse_purchase_price'] ?? 0,
        //         'product_id' => $product->id,
        //         'product_code' => $request['warehouse_product_code'],
        //     ];
        //     DB::table('warehouse_product')->insert($data);
        //     $warehouseProduct = WarehouseProduct::where(['product_id' => $product->id, 'warehouse_id' => $request['warehouse_id']])->first();
        //     $warehouseProduct->updateTotalPrices();
        // }

            DB::commit();
            return response()->json(['message' => 'created successfully', 'data' => $product->id], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Product creation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Product $product)
    {
        $vendor = $this->getActingVendor();
        if ($product->store_id !== ($vendor->store->id ?? null)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        // Load variations relationship
        $product->load('variations');
        return ProductShowResource::make($product);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(ProductUpdateRequest $request, Product $product)
    {
        DB::beginTransaction();
        try {
            $vendor = $this->getActingVendor();
            if ($product->store_id !== ($vendor->store->id ?? null)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
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
        $product->category_id =  $request->category_id;
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

        // Handle new variation structure
        $variations = [];
        if ($request->has('variation_details') && is_array($request->variation_details)) {
            // New variation-based structure
            $variations = $request->variation_details;
        } else {
            // Legacy choice options structure
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
        }
        $product->variations = json_encode($variations);
        $product->variation_name = $request->input('variation_name');

        $product->price = $request->price;
        $product->purchase_price = $request->purchase_price ?? $product->purchase_price;
        $product->quantity = $request->quantity;
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

            // Update ProductVariation records if variation_details provided
            if ($request->has('variation_details') && is_array($request->variation_details)) {
                // Delete existing variations
                ProductVariation::where('product_id', $product->id)->delete();

                // Create new variations
                foreach ($request->variation_details as $detail) {
                    ProductVariation::create([
                        'product_id' => $product->id,
                        'variation_id' => $detail['variation_id'] ?? null,
                        'attribute_value' => $detail['attribute_value'] ?? null,
                        'attribute_id' => $detail['attribute_id'] ?? null,
                        'cost_price' => $detail['cost_price'] ?? $request->purchase_price,
                        'sale_price' => $detail['sale_price'] ?? $request->price,
                        'quantity' => $detail['quantity'] ?? 0,
                        'barcode' => $detail['barcode'] ?? null,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Product update failed: ' . $e->getMessage()], 500);
        }
    }

    public function updateImage(Request $request, Product $product)
    {
        $vendor = $this->getActingVendor();
        if ($product->store_id !== ($vendor->store->id ?? null)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if (!$request->hasFile('image')) {
            return response()->json(['message' => 'No image provided'], 422);
        }

        $product->image = Helpers::upload('product/', 'png', $request->file('image'));
        $product->save();

        return response()->json(['message' => 'Изображение обнавлено', 'image' => $product->image], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        DB::beginTransaction();
        try {
            $vendor = $this->getActingVendor();
            if ($product->store_id !== ($vendor->store->id ?? null)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            if ($product->orders()->exists()) {
                return response()->json([
                    'message' => trans('messages.can_not_delete_product_when_it_has_order')
                ], 422);
            }

            if ($product->image) {
                if (Storage::disk('public')->exists('product/' . $product['image'])) {
                    Storage::disk('public')->delete('product/' . $product['image']);
                }
            }
            $product->delete();

            DB::commit();
            return response()->json(['message' => trans('messages.product_deleted_successfully')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Product deletion failed: ' . $e->getMessage()], 500);
        }
    }

    public function toggleStatus(Request $request, Product $product)
    {
        $vendor = $this->getActingVendor();
        if ($product->store_id !== ($vendor->store->id ?? null)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $request->validate([
            'status' => 'required|boolean|in:1,0'
        ]);
        $product->status = $request->status;
        $product->save();
        return response()->json(['message' => 'status updated successfully']);
    }

    public function export(string $type)
    {
        $vendor = $this->getActingVendor();

        $products = DB::select("
                                SELECT
                                    p.id,
                                    p.name AS название,
                                    p.price AS цена,
                                    w.name AS склад,
                                    c.name AS категория,
                                    w.address AS адрес_склада,
                                    p.quantity AS кол_во,
                                    p.created_at AS создано
                                FROM products p
                                INNER JOIN warehouses w ON w.id = p.warehouse_id
                                INNER JOIN categories c ON c.id = p.category_id
                                WHERE p.store_id = ?
                            ", [$vendor->store->id]);


        $products = json_decode(json_encode($products), true);
        // order_by desc created_at
        usort($products, function ($a, $b) {
            return strtotime($b['создано']) <=> strtotime($a['создано']);
        });

        $time = now()->format('Y-m-d H:i:s');

        switch ($type) {
            case 'excel':
                return (new FastExcel($products))->download("products_$time.xlsx");
                break;
            case 'csv':
                return (new FastExcel($products))->download("products_$time.csv");
                break;

            default:
                return response()->json(['message' => 'укажите тип файла']);
                break;
        }
    }

    public function getTemplate(Request $request)
    {
        // OLD CODE - COMMENTED OUT
        /*
        $request->validate([
            'warehouse_id' => 'required|integer',
            'category_id' => 'required|integer',
            'itemCount' => 'required|integer|min:1|max:500'
        ]);

        $vendor = $this->getActingVendor();
        $category = $vendor->categories()->find($request->category_id);
        $warehouse = $vendor->warehouses()->find($request->warehouse_id);

        if (!$category || !$warehouse) {
            return response()->json(['error' => 'Invalid category or warehouse'], 400);
        }

        $data = [];

        for ($i = 0; $i < $request->itemCount; $i++) {
            $data[] = [
                'name (Название товара)' => null, // name (manual)
                'warehouse_id (Склад)' => $request->warehouse_id,
                'category_id (Категория)' => $request->category_id,
                'price (Цена продажи)' => null, // price (manual)
                'purchase_price (Цена закупки)' => null, // purchase_price (manual)
                'quantity (Количество)' => null, // quantity (manual)
                'description (Описание товара)' => null, // description (manual)
            ];
        }

        $filename = 'product_template_' . time() . '.xlsx';
        $filePath = storage_path('app/public/templates/' . $filename);

        Storage::disk('public')->makeDirectory('templates');

        (new FastExcel(collect($data)))->export($filePath);

        return response()->download($filePath)->deleteFileAfterSend(true);
        */


        $templatePath = public_path('storage/template/products-template-warehouse.xlsx');

        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Template file not found'], 404);
        }

        return response()->download($templatePath, 'products-template-warehouse.xlsx');
    }

    public function importProducts(Request $request, ProductService $productService)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv'
        ]);

        try {
            $data =  (new FastExcel)->import($request->file('file'));

            $processedData =   $productService->removeBrackets($data); // Удаление скобок с ключей


            $validator = Validator::make($processedData->toArray(), $productService->rules);
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            foreach ($processedData as $item) {
                $category = [];
                if ($item['category_id'] != null) {
                    $category[] = [
                        'id' => $item['category_id'],
                        'position' => 1,
                    ];
                }

                Product::query()->create([
                    'name' => $item['name'],
                    'category_ids' => json_encode($category),
                    'category_id' => $item['category_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'price' =>  $item['price'],
                    'purchase_price' => $item['purchase_price'],
                    'quantity' => $item['quantity'],
                    'description' => $item['description'] ?? null,
                    'store_id' => $this->getActingVendor()->store->id,
                    'choice_options' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'attributes' => json_encode([]),
                    'add_ons' => json_encode([]),
                    'variations' => json_encode([]),
                ]);
            }


            return response()->json(['message' => 'Успешно импортированы']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                $th->getCode()
            ], 400);
        }
    }

    public function addProducts(Request $request, Product $product, InventoryService $inventoryService)
    {
        $vendor = $this->getActingVendor();
        if ($product->store_id !== ($vendor->store->id ?? null)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'product_count' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            $qty = (float) $validated['product_count'];
            $price = (float) $validated['purchase_price'];

            $inventoryService->addStock($product, $qty, $price);

            DB::commit();
            $product->refresh();

            return response()->json([
                'message' => 'Stock added successfully',
                'data' => [
                    'product_id' => $product->id,
                    'quantity' => (int) $product->quantity,
                    'purchase_price' => (float) $product->purchase_price,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add stock',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
