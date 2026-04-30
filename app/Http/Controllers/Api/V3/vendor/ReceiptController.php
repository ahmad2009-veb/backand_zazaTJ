<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Models\Receipt;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ReceiptItem;
use App\Models\WarehouseTransfer;
use App\Enums\ReceiptStatusEnum;
use App\Enums\VariationTypeEnum;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V3\Vendor\ReceiptResource;
use App\Http\Traits\VendorEmployeeAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    use VendorEmployeeAccess;

    /**
     * Get all receipts for the vendor
     */
    public function index(Request $request)
    {
        $vendor = $this->getActingVendor();

        $query = Receipt::forVendor($vendor->id);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by warehouse if provided
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by counterparty if provided
        if ($request->has('counterparty_id')) {
            $query->where('counterparty_id', $request->counterparty_id);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $receipts = $query->with(['warehouse', 'counterparty', 'recipientVendor', 'warehouseTransfer', 'originalReceipt', 'items'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ReceiptResource::collection($receipts);
    }

    /**
     * Create a new receipt with products
     */
    public function store(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Convert string booleans to actual booleans for form data
        $input = $request->all();
        if (isset($input['products']) && is_array($input['products'])) {
            foreach ($input['products'] as &$product) {
                if (isset($product['has_variations'])) {
                    $product['has_variations'] = filter_var($product['has_variations'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
            'counterparty_id' => 'required|exists:counterparties,id',
            'status' => 'nullable|string|in:pending,completed',
            'notes' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'nullable|exists:products,id',
            'products.*.product_name' => 'nullable|string|max:255',
            'products.*.product_code' => 'nullable|string|max:255',
            'products.*.category_id' => 'nullable|exists:categories,id',
            'products.*.sub_category_id' => 'nullable|exists:categories,id',
            'products.*.purchase_price' => 'required|numeric|min:0',
            'products.*.retail_price' => 'nullable|numeric|min:0',
            'products.*.quantity' => 'required|numeric|min:0.000001',
            'products.*.has_variations' => 'nullable|boolean',
            'products.*.variation_name' => 'nullable|string',
            'products.*.variation_types' => 'nullable|array',
            'products.*.variation_details' => 'nullable|array',
            'products.*.variation_details.*.variation_id' => 'nullable|string',
            'products.*.variation_details.*.attribute_id' => 'nullable|integer',
            'products.*.variation_details.*.attribute_value' => 'nullable|string',
            'products.*.variation_details.*.cost_price' => 'nullable|numeric|min:0',
            'products.*.variation_details.*.sale_price' => 'nullable|numeric|min:0',
            'products.*.variation_details.*.quantity' => 'nullable|numeric|min:0',
            'products.*.variation_details.*.barcode' => 'nullable|string',
            'products.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        DB::beginTransaction();
        try {
            // Generate receipt number
            $receiptNumber = 'RCP-' . date('YmdHis') . '-' . $vendor->id;

            // Determine receipt status - default to pending if not provided
            $receiptStatus = $validated['status'] ?? ReceiptStatusEnum::PENDING->value;

            // Create receipt
            $receipt = Receipt::create([
                'vendor_id' => $vendor->id,
                'warehouse_id' => $validated['warehouse_id'],
                'counterparty_id' => $validated['counterparty_id'],
                'receipt_number' => $receiptNumber,
                'name' => $validated['name'],
                'status' => $receiptStatus,
                'notes' => $validated['notes'] ?? null,
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            // Process each product
            foreach ($validated['products'] as $productData) {
                $product = null;

                // If product_id is provided, use existing product
                if (!empty($productData['product_id'])) {
                    $product = Product::find($productData['product_id']);
                    if (!$product) {
                        throw new \Exception('Product not found: ' . $productData['product_id']);
                    }
                    // Update product quantity and status based on receipt status
                    $isCompleted = $receiptStatus === ReceiptStatusEnum::COMPLETED->value;
                    $product->quantity = $isCompleted ? $productData['quantity'] : 0;
                    $product->status = $isCompleted ? 1 : 0;
                    $product->save();
                } else {
                    // Always create product, but mark as inactive if receipt is pending
                    $product = new Product();
                    $product->name = $productData['product_name'];
                    $product->product_code = $productData['product_code'] ?? null;
                    $product->price = $productData['retail_price'] ?? $productData['purchase_price'];
                    $product->purchase_price = $productData['purchase_price'];
                    $product->quantity = $receiptStatus === ReceiptStatusEnum::COMPLETED->value ? $productData['quantity'] : 0;
                    $product->warehouse_id = $validated['warehouse_id'];
                    $product->store_id = $vendor->store->id ?? null;
                    // Mark as inactive (0) if pending, active (1) if completed
                    $product->status = $receiptStatus === ReceiptStatusEnum::COMPLETED->value ? 1 : 0;

                    // Handle categories
                    $categories = [];
                    if (!empty($productData['category_id'])) {
                        $categories[] = [
                            'id' => $productData['category_id'],
                            'position' => 1,
                        ];
                    }
                    if (!empty($productData['sub_category_id'])) {
                        $categories[] = [
                            'id' => $productData['sub_category_id'],
                            'position' => 2,
                        ];
                    }
                    if (!empty($categories)) {
                        $product->category_ids = json_encode($categories);
                    }

                    // Handle image upload
                    if ($request->hasFile('products.0.image')) {
                        $imageFile = $request->file('products.0.image');
                        if ($imageFile && $imageFile->isValid()) {
                            $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . "." . $imageFile->getClientOriginalExtension();
                            Storage::disk('public')->put('product/' . $imageName, file_get_contents($imageFile));
                            $product->image = $imageName;
                        }
                    }

                    // Handle variations
                    if (!empty($productData['variation_details']) && is_array($productData['variation_details'])) {
                        // Use provided variation_name if available, otherwise extract from variation_id
                        if (!empty($productData['variation_name'])) {
                            // Use the provided variation_name directly
                            $product->variation_name = $productData['variation_name'];
                        } else {
                            // Fallback: Extract variation_type from the first variation_id
                            // Format: {variation_type}_{timestamp}_{index}
                            // We extract everything before the first underscore
                            $firstVariationId = $productData['variation_details'][0]['variation_id'] ?? null;
                            if ($firstVariationId) {
                                $firstUnderscorePos = strpos($firstVariationId, '_');
                                if ($firstUnderscorePos !== false) {
                                    // Extract everything before the first underscore
                                    $product->variation_name = substr($firstVariationId, 0, $firstUnderscorePos);
                                }
                            }
                        }
                        $product->variations = json_encode($productData['variation_details']);
                    }

                    $product->save();
                }

                // Create receipt items only if product exists
                if ($product) {
                    if (!empty($productData['variation_details']) && is_array($productData['variation_details'])) {
                        // Determine variation type based on count of variation_details
                        $variationCount = count($productData['variation_details']);
                        $variationType = $variationCount === 1
                            ? VariationTypeEnum::SINGLE
                            : VariationTypeEnum::MULTIPLE;

                        // Create receipt items for each variation
                        foreach ($productData['variation_details'] as $variationDetail) {
                            $quantity = $variationDetail['quantity'] ?? 0;
                            $unitPrice = $variationDetail['cost_price'] ?? $productData['purchase_price'];
                            $totalPrice = $quantity * $unitPrice;

                            // Create or get product variation
                            $productVariation = ProductVariation::create([
                                'product_id' => $product->id,
                                'variation_id' => $variationDetail['variation_id'] ?? null,
                                'attribute_id' => $variationDetail['attribute_id'] ?? null,
                                'attribute_value' => $variationDetail['attribute_value'] ?? null,
                                'cost_price' => $unitPrice,
                                'sale_price' => $variationDetail['sale_price'] ?? $unitPrice,
                                'quantity' => $quantity,
                                'barcode' => $variationDetail['barcode'] ?? null,
                            ]);

                            // Create receipt item with variation type metadata
                            ReceiptItem::create([
                                'receipt_id' => $receipt->id,
                                'product_id' => $product->id,
                                'product_variation_id' => $productVariation->id,
                                'quantity' => $quantity,
                                'unit_price' => $unitPrice,
                                'total_price' => $totalPrice,
                                'notes' => json_encode(['variation_type' => $variationType->value]),
                            ]);

                            $totalAmount += $totalPrice;
                        }
                    } else {
                        // Create single receipt item without variation
                        $quantity = $productData['quantity'];
                        $unitPrice = $productData['purchase_price'];
                        $totalPrice = $quantity * $unitPrice;

                        ReceiptItem::create([
                            'receipt_id' => $receipt->id,
                            'product_id' => $product->id,
                            'product_variation_id' => null,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total_price' => $totalPrice,
                        ]);

                        $totalAmount += $totalPrice;
                    }
                }
            }

            // Update receipt total amount
            $receipt->update(['total_amount' => $totalAmount]);
            $receipt->load(['warehouse', 'counterparty', 'recipientVendor', 'warehouseTransfer', 'items.product', 'items.productVariation']);

            DB::commit();

            return response()->json([
                'message' => 'Квитанция успешно создана',
                'data' => new ReceiptResource($receipt)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при создании квитанции: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single receipt with its items
     */
    public function show(Receipt $receipt)
    {
        $vendor = $this->getActingVendor();

        // Check if receipt belongs to vendor
        if ($receipt->vendor_id !== $vendor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $receipt->load(['warehouse', 'counterparty', 'recipientVendor', 'warehouseTransfer', 'items.product', 'items.productVariation']);

        return new ReceiptResource($receipt);
    }

    /**
     * Update an existing receipt with products
     */
    public function update(Request $request, Receipt $receipt)
    {
        $vendor = $this->getActingVendor();

        // Check if receipt belongs to vendor
        if ($receipt->vendor_id !== $vendor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Convert string booleans to actual booleans for form data
        $input = $request->all();
        if (isset($input['products']) && is_array($input['products'])) {
            foreach ($input['products'] as &$product) {
                if (isset($product['has_variations'])) {
                    $product['has_variations'] = filter_var($product['has_variations'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'counterparty_id' => 'nullable|exists:counterparties,id',
            'status' => 'nullable|string|in:pending,completed',
            'refund' => 'nullable|boolean', // Keep for backward compatibility
            'refund_items' => 'nullable|array',
            'refund_items.*.receipt_item_id' => 'required_with:refund_items|exists:receipt_items,id',
            'refund_items.*.quantity' => 'required_with:refund_items|numeric|min:0.000001',
            'notes' => 'nullable|string',
            'products' => 'nullable|array',
            'products.*.product_id' => 'nullable|exists:products,id',
            'products.*.product_name' => 'nullable|string|max:255',
            'products.*.product_code' => 'nullable|string|max:255',
            'products.*.category_id' => 'nullable|exists:categories,id',
            'products.*.sub_category_id' => 'nullable|exists:categories,id',
            'products.*.purchase_price' => 'nullable|numeric|min:0',
            'products.*.retail_price' => 'nullable|numeric|min:0',
            'products.*.quantity' => 'nullable|numeric|min:0.000001',
            'products.*.has_variations' => 'nullable|boolean',
            'products.*.variation_name' => 'nullable|string',
            'products.*.variation_types' => 'nullable|array',
            'products.*.variation_details' => 'nullable|array',
            'products.*.variation_details.*.variation_id' => 'nullable|string',
            'products.*.variation_details.*.attribute_id' => 'nullable|integer',
            'products.*.variation_details.*.attribute_value' => 'nullable|string',
            'products.*.variation_details.*.cost_price' => 'nullable|numeric|min:0',
            'products.*.variation_details.*.sale_price' => 'nullable|numeric|min:0',
            'products.*.variation_details.*.quantity' => 'nullable|numeric|min:0',
            'products.*.variation_details.*.barcode' => 'nullable|string',
            'products.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        DB::beginTransaction();
        try {
            // Track old status for status change handling
            $oldStatus = $receipt->status;

            // Update receipt basic info
            if (isset($validated['name'])) {
                $receipt->name = $validated['name'];
            }
            if (isset($validated['warehouse_id'])) {
                $receipt->warehouse_id = $validated['warehouse_id'];
            }
            if (isset($validated['counterparty_id'])) {
                $receipt->counterparty_id = $validated['counterparty_id'];
            }
            if (isset($validated['status'])) {
                $receipt->status = ReceiptStatusEnum::from($validated['status']);
            }
            if (isset($validated['notes'])) {
                $receipt->notes = $validated['notes'];
            }

            // Ensure receipt status is always an enum for consistent comparison
            if (!($receipt->status instanceof ReceiptStatusEnum)) {
                $receipt->status = ReceiptStatusEnum::from($receipt->status);
            }

            // Check if this is a refund request BEFORE handling status changes
            // If refunding, we should NOT move products to accepting vendor's warehouse
            $isRefundRequest = (isset($validated['refund']) && $validated['refund'] === true) 
                || !empty($validated['refund_items']);
            
            // Track if we need to update product quantities (for completed receipts)
            $shouldUpdateProductQuantities = $receipt->status === ReceiptStatusEnum::COMPLETED;
            
            // Update products FIRST if provided (including categories)
            // This ensures categories are set BEFORE handleTransferReceiptCompletion is called
            if (!empty($validated['products'])) {
                foreach ($validated['products'] as $productIndex => $productData) {
                    if (!empty($productData['product_id'])) {
                        $product = Product::find($productData['product_id']);
                        if ($product) {
                            // Update categories if provided
                            $categories = [];
                            if (!empty($productData['category_id'])) {
                                $categories[] = [
                                    'id' => $productData['category_id'],
                                    'position' => 1,
                                ];
                            }
                            if (!empty($productData['sub_category_id'])) {
                                $categories[] = [
                                    'id' => $productData['sub_category_id'],
                                    'position' => 2,
                                ];
                            }
                            // Only update category_ids if categories are provided
                            if (!empty($categories)) {
                                $product->category_ids = json_encode($categories);
                                $product->save(); // Save immediately so it's available for handleTransferReceiptCompletion
                            } elseif (isset($productData['category_id']) && empty($productData['category_id']) && 
                                      isset($productData['sub_category_id']) && empty($productData['sub_category_id'])) {
                                // Both explicitly set to empty/null - clear categories
                                $product->category_ids = null;
                                $product->save();
                            }
                        }
                    }
                }
            }
            
            // Handle status changes
            $oldStatusValue = $oldStatus instanceof ReceiptStatusEnum ? $oldStatus->value : $oldStatus;
            $newStatusValue = $receipt->status instanceof ReceiptStatusEnum ? $receipt->status->value : $receipt->status;

            if ($oldStatusValue !== $newStatusValue) {
                $receiptItems = ReceiptItem::where('receipt_id', $receipt->id)->with('product', 'productVariation')->get();

                // If status changed from pending to completed, activate existing products
                // BUT skip if this is a refund request - products should go back to sender, not to accepting vendor
                if ($oldStatusValue === ReceiptStatusEnum::PENDING->value && $newStatusValue === ReceiptStatusEnum::COMPLETED->value && !$isRefundRequest) {
                    // Set received_at timestamp
                    if (!$receipt->received_at) {
                        $receipt->received_at = now();
                        $receipt->save();
                    }

                    // Check if this is a transfer receipt
                    if ($receipt->warehouse_transfer_id) {
                        // Handle transfer receipt - move products to warehouse and set quantities
                        $this->handleTransferReceiptCompletion($receipt, $receiptItems);
                    } else {
                        // Regular receipt - just activate products
                        foreach ($receiptItems as $item) {
                            if ($item->product) {
                                // Update product to active and set quantity from receipt item
                                $item->product->status = 1;
                                $item->product->quantity = $item->quantity;
                                $item->product->save();
                            }
                        }
                    }
                }
                // If status changed from completed to pending, deactivate existing products
                elseif ($oldStatusValue === ReceiptStatusEnum::COMPLETED->value && $newStatusValue === ReceiptStatusEnum::PENDING->value) {
                    // Check if this is a transfer receipt
                    if ($receipt->warehouse_transfer_id) {
                        // Handle transfer receipt - move products back to transit (warehouse_id = null)
                        foreach ($receiptItems as $item) {
                            if ($item->product) {
                                $item->product->warehouse_id = null; // Back to transit
                                $item->product->status = 0;
                                // Reset quantities - will be set again when receipt is completed
                                if ($item->product_variation_id) {
                                    $variation = $item->productVariation;
                                    if ($variation) {
                                        $variation->quantity = 0;
                                        $variation->save();
                                    }
                                } else {
                                    $item->product->quantity = 0;
                                }
                                $item->product->save();
                            }
                        }
                    } else {
                        // Regular receipt - just deactivate products
                        foreach ($receiptItems as $item) {
                            if ($item->product) {
                                // Update product to inactive and set quantity to 0
                                $item->product->status = 0;
                                $item->product->quantity = 0;
                                $item->product->save();
                            }
                        }
                    }
                }
            }

            // Handle refund request (already checked above for status change logic)
            // Support both old refund boolean (full refund) and new refund_items array (partial refund)
            if ($isRefundRequest) {
                // Refund can only be requested for transfer receipts
                // Allowed for pending (inspection/verification phase) or completed receipts
                // Check the original status before any updates
                $currentStatusValue = $oldStatus instanceof ReceiptStatusEnum ? $oldStatus->value : $oldStatus;
                
                if (!$receipt->warehouse_transfer_id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Возврат возможен только для квитанций перемещения'
                    ], 422);
                }
                
                // Allow refund for pending (inspection phase) or completed receipts
                $allowedStatuses = [
                    ReceiptStatusEnum::PENDING->value,
                    ReceiptStatusEnum::COMPLETED->value
                ];
                
                if (!in_array($currentStatusValue, $allowedStatuses)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Возврат возможен только для квитанций в статусе "Черновик" или "Проведен"'
                    ], 422);
                }

                // Check if refund already exists (any status - pending or completed)
                // Once a refund is requested (even if completed), no more refunds allowed
                $existingRefund = Receipt::where('original_receipt_id', $receipt->id)->first();
                
                if ($existingRefund) {
                    DB::rollBack();
                    $statusLabel = $existingRefund->status === ReceiptStatusEnum::COMPLETED ? 'принят' : 'запрошен';
                    return response()->json([
                        'message' => "Возврат уже {$statusLabel} для этой квитанции"
                    ], 422);
                }

                // Get refund items - either from refund_items array or all items if refund=true
                $refundItemsData = [];
                if (!empty($validated['refund_items'])) {
                    // Partial refund - validate quantities
                    $receiptItems = ReceiptItem::where('receipt_id', $receipt->id)
                        ->whereIn('id', array_column($validated['refund_items'], 'receipt_item_id'))
                        ->get()
                        ->keyBy('id');
                    
                    foreach ($validated['refund_items'] as $refundItem) {
                        $receiptItemId = $refundItem['receipt_item_id'];
                        $refundQuantity = (float) $refundItem['quantity'];
                        
                        if (!isset($receiptItems[$receiptItemId])) {
                            DB::rollBack();
                            return response()->json([
                                'message' => "Элемент квитанции с ID {$receiptItemId} не найден"
                            ], 422);
                        }
                        
                        $originalItem = $receiptItems[$receiptItemId];
                        $originalQuantity = (float) $originalItem->quantity;
                        
                        if ($refundQuantity > $originalQuantity) {
                            DB::rollBack();
                            return response()->json([
                                'message' => "Количество возврата ({$refundQuantity}) превышает количество в квитанции ({$originalQuantity}) для элемента ID {$receiptItemId}"
                            ], 422);
                        }
                        
                        $refundItemsData[] = [
                            'receipt_item' => $originalItem,
                            'quantity' => $refundQuantity,
                        ];
                    }
                } else {
                    // Full refund (backward compatibility with refund=true)
                    $allReceiptItems = ReceiptItem::where('receipt_id', $receipt->id)->get();
                    foreach ($allReceiptItems as $item) {
                        $refundItemsData[] = [
                            'receipt_item' => $item,
                            'quantity' => (float) $item->quantity,
                        ];
                    }
                }

                $this->handleRefundRequest($receipt, $vendor, $refundItemsData);
            }

            // Track if we need to update product quantities (for completed receipts)
            $shouldUpdateProductQuantities = $newStatusValue === ReceiptStatusEnum::COMPLETED->value;

            // If products are provided, update receipt items
            if (!empty($validated['products'])) {
                // Collect variation IDs that are being updated
                $variationIdsInUpdate = [];
                foreach ($validated['products'] as $productData) {
                    if (!empty($productData['variation_details']) && is_array($productData['variation_details'])) {
                        foreach ($productData['variation_details'] as $variationDetail) {
                            $variationIdsInUpdate[] = $variationDetail['variation_id'] ?? null;
                        }
                    }
                }

                // Delete receipt items for variations that are no longer in the update
                if (!empty($variationIdsInUpdate)) {
                    ReceiptItem::where('receipt_id', $receipt->id)
                        ->whereHas('productVariation', function ($query) use ($variationIdsInUpdate) {
                            $query->whereNotIn('variation_id', $variationIdsInUpdate);
                        })
                        ->delete();
                } else {
                    // If no variations in update, delete all receipt items
                    ReceiptItem::where('receipt_id', $receipt->id)->delete();
                }

                $totalAmount = 0;

                // Process each product
                foreach ($validated['products'] as $productIndex => $productData) {
                    $product = null;

                    // If product_id is provided, use existing product
                    if (!empty($productData['product_id'])) {
                        $product = Product::find($productData['product_id']);
                        if (!$product) {
                            throw new \Exception('Product not found: ' . $productData['product_id']);
                        }
                        // Always update product quantity and status based on receipt status
                        // This ensures quantities are updated even when status doesn't change
                        $product->quantity = $shouldUpdateProductQuantities ? $productData['quantity'] : 0;
                        $product->status = $shouldUpdateProductQuantities ? 1 : 0;

                        // Update variation_name if provided
                        if (!empty($productData['variation_name'])) {
                            $product->variation_name = $productData['variation_name'];
                        }

                        // Handle categories - update if provided, leave null if not provided
                        $categories = [];
                        if (!empty($productData['category_id'])) {
                            $categories[] = [
                                'id' => $productData['category_id'],
                                'position' => 1,
                            ];
                        }
                        if (!empty($productData['sub_category_id'])) {
                            $categories[] = [
                                'id' => $productData['sub_category_id'],
                                'position' => 2,
                            ];
                        }
                        // Only update category_ids if categories are provided, otherwise leave as is (null or existing)
                        if (!empty($categories)) {
                            $product->category_ids = json_encode($categories);
                        } elseif (isset($productData['category_id']) && empty($productData['category_id']) && 
                                  isset($productData['sub_category_id']) && empty($productData['sub_category_id'])) {
                            // Both explicitly set to empty/null - clear categories
                            $product->category_ids = null;
                        }

                        // Handle image upload if provided
                        if ($request->hasFile("products.$productIndex.image")) {
                            $imageFile = $request->file("products.$productIndex.image");
                            if ($imageFile && $imageFile->isValid()) {
                                // Delete old image if exists
                                if ($product->image && Storage::disk('public')->exists('product/' . $product->image)) {
                                    Storage::disk('public')->delete('product/' . $product->image);
                                }
                                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . "." . $imageFile->getClientOriginalExtension();
                                Storage::disk('public')->put('product/' . $imageName, file_get_contents($imageFile));
                                $product->image = $imageName;
                            }
                        }

                        $product->save();
                    } else {
                        // Always create product, but mark as inactive if receipt is pending
                        $product = new Product();
                        $product->name = $productData['product_name'];
                        $product->product_code = $productData['product_code'] ?? null;
                        $product->price = $productData['retail_price'] ?? $productData['purchase_price'];
                        $product->purchase_price = $productData['purchase_price'];
                        $isCompleted = $receipt->status === ReceiptStatusEnum::COMPLETED;
                        $product->quantity = $isCompleted ? $productData['quantity'] : 0;
                        $product->warehouse_id = $receipt->warehouse_id;
                        $product->store_id = $vendor->store->id ?? null;
                        // Mark as inactive (0) if pending, active (1) if completed
                        $product->status = $isCompleted ? 1 : 0;

                        // Handle categories - set if provided, leave null if not provided
                        $categories = [];
                        if (!empty($productData['category_id'])) {
                            $categories[] = [
                                'id' => $productData['category_id'],
                                'position' => 1,
                            ];
                        }
                        if (!empty($productData['sub_category_id'])) {
                            $categories[] = [
                                'id' => $productData['sub_category_id'],
                                'position' => 2,
                            ];
                        }
                        // Only set category_ids if categories are provided, otherwise leave as null
                        if (!empty($categories)) {
                            $product->category_ids = json_encode($categories);
                        } else {
                            // No categories provided - leave as null (don't set if not provided)
                            $product->category_ids = null;
                        }

                        // Handle image upload
                        if ($request->hasFile("products.$productIndex.image")) {
                            $imageFile = $request->file("products.$productIndex.image");
                            if ($imageFile && $imageFile->isValid()) {
                                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . "." . $imageFile->getClientOriginalExtension();
                                Storage::disk('public')->put('product/' . $imageName, file_get_contents($imageFile));
                                $product->image = $imageName;
                            }
                        }

                        // Handle variations
                        if (!empty($productData['variation_details']) && is_array($productData['variation_details'])) {
                            // Use provided variation_name if available, otherwise extract from variation_id
                            if (!empty($productData['variation_name'])) {
                                // Use the provided variation_name directly
                                $product->variation_name = $productData['variation_name'];
                            } else {
                                // Fallback: Extract variation_name from the first variation_id
                                // Format: {variation_type}_{timestamp}_{index}
                                // We extract everything before the first underscore
                                $firstVariationId = $productData['variation_details'][0]['variation_id'] ?? null;
                                if ($firstVariationId) {
                                    $firstUnderscorePos = strpos($firstVariationId, '_');
                                    if ($firstUnderscorePos !== false) {
                                        // Extract everything before the first underscore
                                        $product->variation_name = substr($firstVariationId, 0, $firstUnderscorePos);
                                    }
                                }
                            }
                            $product->variations = json_encode($productData['variation_details']);
                        }

                        $product->save();
                    }

                    // Create receipt items only if product exists
                    if ($product) {
                        if (!empty($productData['variation_details']) && is_array($productData['variation_details'])) {
                            // Determine variation type based on count of variation_details
                            $variationCount = count($productData['variation_details']);
                            $variationType = $variationCount === 1
                                ? VariationTypeEnum::SINGLE
                                : VariationTypeEnum::MULTIPLE;

                            // Create receipt items for each variation
                            foreach ($productData['variation_details'] as $variationDetail) {
                                $quantity = $variationDetail['quantity'] ?? 0;
                                $unitPrice = $variationDetail['cost_price'] ?? $productData['purchase_price'];
                                $totalPrice = $quantity * $unitPrice;

                                // Update or create product variation
                                $productVariation = ProductVariation::updateOrCreate(
                                    [
                                        'product_id' => $product->id,
                                        'variation_id' => $variationDetail['variation_id'] ?? null,
                                    ],
                                    [
                                        'attribute_id' => $variationDetail['attribute_id'] ?? null,
                                        'attribute_value' => $variationDetail['attribute_value'] ?? null,
                                        'cost_price' => $unitPrice,
                                        'sale_price' => $variationDetail['sale_price'] ?? $unitPrice,
                                        'quantity' => $quantity,
                                        'barcode' => $variationDetail['barcode'] ?? null,
                                    ]
                                );

                                // Update or create receipt item with variation type metadata
                                ReceiptItem::updateOrCreate(
                                    [
                                        'receipt_id' => $receipt->id,
                                        'product_id' => $product->id,
                                        'product_variation_id' => $productVariation->id,
                                    ],
                                    [
                                        'quantity' => $quantity,
                                        'unit_price' => $unitPrice,
                                        'total_price' => $totalPrice,
                                        'notes' => json_encode(['variation_type' => $variationType->value]),
                                    ]
                                );

                                $totalAmount += $totalPrice;
                            }
                        } else {
                            // Create single receipt item without variation
                            $quantity = $productData['quantity'];
                            $unitPrice = $productData['purchase_price'];
                            $totalPrice = $quantity * $unitPrice;

                            ReceiptItem::create([
                                'receipt_id' => $receipt->id,
                                'product_id' => $product->id,
                                'product_variation_id' => null,
                                'quantity' => $quantity,
                                'unit_price' => $unitPrice,
                                'total_price' => $totalPrice,
                            ]);

                            $totalAmount += $totalPrice;
                        }
                    }
                }

                // Update receipt total amount
                $receipt->total_amount = $totalAmount;
            }

            $receipt->save();
            $receipt->load(['warehouse', 'counterparty', 'recipientVendor', 'warehouseTransfer', 'items.product', 'items.productVariation']);

            DB::commit();

            return response()->json([
                'message' => 'Квитанция успешно обновлена',
                'data' => new ReceiptResource($receipt)
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при обновлении квитанции: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get receipts by status
     */
    public function byStatus(Request $request, $status)
    {
        $vendor = $this->getActingVendor();

        $perPage = $request->get('per_page', 15);
        $receipts = Receipt::forVendor($vendor->id)
            ->byStatus($status)
            ->with(['warehouse', 'counterparty', 'recipientVendor', 'warehouseTransfer', 'items'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ReceiptResource::collection($receipts);
    }

    /**
     * Get receipts statistics
     */
    public function statistics(Request $request)
    {
        $vendor = $this->getActingVendor();

        $stats = [
            'total_receipts' => Receipt::forVendor($vendor->id)->count(),
            'pending_receipts' => Receipt::forVendor($vendor->id)->byStatus('pending')->count(),
            'completed_receipts' => Receipt::forVendor($vendor->id)->byStatus('completed')->count(),
            'total_amount' => Receipt::forVendor($vendor->id)->sum('total_amount'),
        ];

        return response()->json($stats);
    }

    /**
     * Handle transfer receipt completion - move products to warehouse and set quantities
     */
    private function handleTransferReceiptCompletion(Receipt $receipt, $receiptItems): void
    {
        $warehouseId = $receipt->warehouse_id;
        
        // Check if this is a refund receipt - restore store_id to sender's store
        $isRefundReceipt = !empty($receipt->original_receipt_id);
        $receiptVendorStoreId = null;
        if ($isRefundReceipt) {
            // Load vendor with store relationship if not already loaded
            if (!$receipt->relationLoaded('vendor')) {
                $receipt->load('vendor.store');
            } elseif (!$receipt->vendor || !$receipt->vendor->relationLoaded('store')) {
                $receipt->vendor?->load('store');
            }
            $receiptVendorStoreId = $receipt->vendor?->store?->id;
        }
        
        // Group items by product_id to handle multiple variations of the same product
        $itemsByProduct = [];
        foreach ($receiptItems as $item) {
            if (!$item->product) {
                continue; // Skip items with missing products
            }
            $productId = $item->product_id;
            if (!isset($itemsByProduct[$productId])) {
                $itemsByProduct[$productId] = [];
            }
            $itemsByProduct[$productId][] = $item;
        }

        // Process each product group
        foreach ($itemsByProduct as $productId => $items) {
            $incomingProduct = $items[0]->product;
            if (!$incomingProduct) {
                continue;
            }

            // Check if a matching product already exists in recipient's warehouse (by name)
            $existingProduct = Product::where('warehouse_id', $warehouseId)
                ->where('name', $incomingProduct->name)
                ->where('id', '!=', $incomingProduct->id)
                ->first();

            if ($existingProduct) {
                // Merge all variations into existing product
                foreach ($items as $item) {
                    $transferQuantity = $item->quantity;
                    $variationId = $item->product_variation_id;
                    $senderSalePrice = $item->unit_price;

                    if ($variationId) {
                        // Find the source variation to get its variation_id string
                        $sourceVariation = ProductVariation::find($variationId);

                        if ($sourceVariation) {
                            // Find or create matching variation on existing product
                            $existingVariation = ProductVariation::where('product_id', $existingProduct->id)
                                ->where('variation_id', $sourceVariation->variation_id)
                                ->first();

                            if ($existingVariation) {
                                // Add quantity to existing variation
                                $existingVariation->quantity += $transferQuantity;
                                $existingVariation->cost_price = $senderSalePrice;
                                $existingVariation->sale_price = $senderSalePrice;
                                $existingVariation->save();
                            } else {
                                // Create new variation on existing product
                                ProductVariation::create([
                                    'product_id' => $existingProduct->id,
                                    'variation_id' => $sourceVariation->variation_id,
                                    'attribute_id' => $sourceVariation->attribute_id,
                                    'attribute_value' => $sourceVariation->attribute_value,
                                    'quantity' => $transferQuantity,
                                    'cost_price' => $senderSalePrice,
                                    'sale_price' => $senderSalePrice,
                                    'barcode' => $sourceVariation->barcode,
                                ]);
                            }
                        }
                    } else {
                        // No variation - add to first/default variation of existing product
                        $defaultVariation = $existingProduct->variations()->first();
                        if ($defaultVariation) {
                            $defaultVariation->quantity += $transferQuantity;
                            $defaultVariation->cost_price = $senderSalePrice;
                            $defaultVariation->sale_price = $senderSalePrice;
                            $defaultVariation->save();
                        } else {
                            // Product has no variations - add to product quantity directly
                            $existingProduct->quantity = ($existingProduct->quantity ?? 0) + $transferQuantity;
                        }
                    }

                    // Update receipt item to reference existing product
                    $item->product_id = $existingProduct->id;
                    $item->save();
                }

                // Update product quantity to sum of all variations (if product has variations)
                if ($existingProduct->variations()->count() > 0) {
                    $existingProduct->quantity = $existingProduct->variations()->sum('quantity');
                }
                // For refund receipts, restore store_id to sender's store
                if ($isRefundReceipt && $receiptVendorStoreId) {
                    $existingProduct->store_id = $receiptVendorStoreId;
                }
                $existingProduct->status = 1; // Activate
                $existingProduct->save();

                // Delete the incoming duplicate product(s) - but only if all items reference the same product
                // Check if all items reference the same incoming product
                $allSameProduct = true;
                foreach ($items as $item) {
                    if ($item->product_id != $incomingProduct->id) {
                        $allSameProduct = false;
                        break;
                    }
                }
                
                if ($allSameProduct) {
                    $incomingProduct->variations()->delete();
                    $incomingProduct->delete();
                }
            } else {
                // No matching product - check if we need to merge multiple incoming products with same name
                $productsToMerge = [];
                foreach ($items as $item) {
                    if ($item->product && $item->product->name === $incomingProduct->name) {
                        $productsToMerge[$item->product_id] = $item->product;
                    }
                }

                // If we have multiple products with same name, merge them into one
                if (count($productsToMerge) > 1) {
                    // Use the first product as the base
                    $baseProduct = $incomingProduct;
                    $baseProduct->warehouse_id = $warehouseId;
                    $baseProduct->status = 1;

                    // Collect all variations from all products
                    $allVariations = [];
                    foreach ($items as $item) {
                        $transferQuantity = $item->quantity;
                        $variationId = $item->product_variation_id;
                        $senderSalePrice = $item->unit_price;

                        if ($variationId) {
                            $sourceVariation = ProductVariation::find($variationId);
                            if ($sourceVariation && $sourceVariation->product_id == $item->product_id) {
                                // Check if variation already exists in base product
                                $existingVariation = ProductVariation::where('product_id', $baseProduct->id)
                                    ->where('variation_id', $sourceVariation->variation_id)
                                    ->first();

                                if ($existingVariation) {
                                    $existingVariation->quantity += $transferQuantity;
                                    $existingVariation->cost_price = $senderSalePrice;
                                    $existingVariation->sale_price = $senderSalePrice;
                                    $existingVariation->save();
                                } else {
                                    // Clone variation to base product
                                    $newVariation = $sourceVariation->replicate();
                                    $newVariation->product_id = $baseProduct->id;
                                    $newVariation->quantity = $transferQuantity;
                                    $newVariation->cost_price = $senderSalePrice;
                                    $newVariation->sale_price = $senderSalePrice;
                                    $newVariation->save();
                                }
                            }
                        } else {
                            // No variation - update first/default variation
                            $firstVariation = $baseProduct->variations()->first();
                            if ($firstVariation) {
                                $firstVariation->quantity += $transferQuantity;
                                $firstVariation->cost_price = $senderSalePrice;
                                $firstVariation->sale_price = $senderSalePrice;
                                $firstVariation->save();
                            } else {
                                $baseProduct->quantity = ($baseProduct->quantity ?? 0) + $transferQuantity;
                            }
                        }

                        // Update receipt item to reference base product
                        $item->product_id = $baseProduct->id;
                        $item->save();
                    }

                    // Set prices on base product
                    $firstItem = $items[0];
                    $baseProduct->purchase_price = $firstItem->unit_price;
                    $baseProduct->price = $firstItem->unit_price;
                    
                    // For refund receipts, restore store_id to sender's store
                    if ($isRefundReceipt && $receiptVendorStoreId) {
                        $baseProduct->store_id = $receiptVendorStoreId;
                    }
                    
                    // Update product quantity to sum of all variations
                    if ($baseProduct->variations()->count() > 0) {
                        $baseProduct->quantity = $baseProduct->variations()->sum('quantity');
                    }
                    $baseProduct->save();

                    // Delete duplicate products (except the base one)
                    foreach ($productsToMerge as $productIdToDelete => $productToDelete) {
                        if ($productIdToDelete != $baseProduct->id) {
                            $productToDelete->variations()->delete();
                            $productToDelete->delete();
                        }
                    }
                } else {
                    // Single product - assign warehouse and update prices/quantities
                    $incomingProduct->warehouse_id = $warehouseId;
                    $incomingProduct->status = 1;

                    foreach ($items as $item) {
                        $transferQuantity = $item->quantity;
                        $variationId = $item->product_variation_id;
                        $senderSalePrice = $item->unit_price;

                        // Update variation prices and quantities
                        if ($variationId) {
                            $variation = ProductVariation::find($variationId);
                            if ($variation && $variation->product_id == $incomingProduct->id) {
                                $variation->cost_price = $senderSalePrice;
                                $variation->sale_price = $senderSalePrice;
                                $variation->quantity = $transferQuantity;
                                $variation->save();
                            }
                        } else {
                            // No variation - update first/default variation
                            $firstVariation = $incomingProduct->variations()->first();
                            if ($firstVariation) {
                                $firstVariation->cost_price = $senderSalePrice;
                                $firstVariation->sale_price = $senderSalePrice;
                                $firstVariation->quantity = $transferQuantity;
                                $firstVariation->save();
                            } else {
                                $incomingProduct->quantity = $transferQuantity;
                            }
                        }
                    }

                    // Set prices on product
                    $firstItem = $items[0];
                    $incomingProduct->purchase_price = $firstItem->unit_price;
                    $incomingProduct->price = $firstItem->unit_price;
                    
                    // For refund receipts, restore store_id to sender's store
                    if ($isRefundReceipt && $receiptVendorStoreId) {
                        $incomingProduct->store_id = $receiptVendorStoreId;
                    }
                    
                    // Update product quantity to sum of all variations
                    if ($incomingProduct->variations()->count() > 0) {
                        $incomingProduct->quantity = $incomingProduct->variations()->sum('quantity');
                    }
                    $incomingProduct->save();
                }
            }
        }
    }

    /**
     * Handle refund request - create a refund receipt for the sender
     * 
     * @param Receipt $receipt The original receipt being refunded
     * @param $requestingVendor The vendor requesting the refund
     * @param array $refundItemsData Array of ['receipt_item' => ReceiptItem, 'quantity' => float]
     */
    private function handleRefundRequest(Receipt $receipt, $requestingVendor, array $refundItemsData): void
    {
        // Load the transfer to get the sender vendor
        $transfer = WarehouseTransfer::with('vendor')->find($receipt->warehouse_transfer_id);
        if (!$transfer) {
            throw new \Exception('Перемещение не найдено');
        }

        $senderVendor = $transfer->vendor;
        if (!$senderVendor) {
            throw new \Exception('Отправитель не найден');
        }

        // Load products and variations for refund items
        $refundItems = [];
        $totalAmount = 0;
        
        foreach ($refundItemsData as $refundData) {
            $receiptItem = $refundData['receipt_item'];
            $refundQuantity = $refundData['quantity'];
            
            // Load product and variation
            $receiptItem->load('product', 'productVariation');
            
            if (!$receiptItem->product) {
                continue; // Skip if product not found
            }
            
            // Deduct quantities from products/variations only if receipt is completed
            // For pending receipts, products haven't been moved to warehouse yet (still in transit)
            if ($receipt->status === ReceiptStatusEnum::COMPLETED->value) {
                // Receipt is completed - products are in warehouse, so deduct quantities
                if ($receiptItem->product_variation_id) {
                    // Product with variation
                    $variation = $receiptItem->productVariation;
                    if ($variation) {
                        $currentVariationQty = (float) $variation->quantity;
                        $newVariationQty = max(0, $currentVariationQty - $refundQuantity);
                        $variation->quantity = $newVariationQty;
                        $variation->save();
                        
                        // Update product quantity (sum of all variations)
                        $allVariations = $receiptItem->product->variations()->get();
                        $receiptItem->product->quantity = $allVariations->sum('quantity');
                    }
                } else {
                    // Product without variation
                    $currentProductQty = (float) $receiptItem->product->quantity;
                    $newProductQty = max(0, $currentProductQty - $refundQuantity);
                    $receiptItem->product->quantity = $newProductQty;
                }
                
                // If product quantity becomes 0, move it back to transit
                if ($receiptItem->product->quantity <= 0) {
                    $receiptItem->product->warehouse_id = null; // Back to transit
                    $receiptItem->product->status = 0; // Inactive
                }
                
                $receiptItem->product->save();
            }
            // For pending receipts: products are still in transit, no need to deduct
            // Products will be restored to sender when refund receipt is completed
            
            // Calculate refund amount for this item
            $unitPrice = (float) ($receiptItem->unit_price ?? 0);
            $itemTotalPrice = $unitPrice * $refundQuantity;
            $totalAmount += $itemTotalPrice;
            
            // Store refund item data
            $refundItems[] = [
                'receipt_item' => $receiptItem,
                'quantity' => $refundQuantity,
                'unit_price' => $unitPrice,
                'total_price' => $itemTotalPrice,
            ];
        }

        // Generate refund receipt number
        $refundReceiptNumber = 'RCP-REF-' . date('YmdHis') . '-' . $senderVendor->id;

        // Create refund receipt for the sender
        $refundReceipt = Receipt::create([
            'vendor_id' => $senderVendor->id,
            'warehouse_id' => $transfer->from_warehouse_id, // Sender's warehouse
            'counterparty_id' => null,
            'recipient_vendor_id' => $requestingVendor->id, // The vendor requesting refund
            'warehouse_transfer_id' => $transfer->id,
            'original_receipt_id' => $receipt->id, // Link to the original receipt
            'receipt_number' => $refundReceiptNumber,
            'name' => 'Возврат: ' . ($receipt->name ?? $receipt->receipt_number),
            'status' => ReceiptStatusEnum::PENDING, // Pending approval from sender
            'total_amount' => $totalAmount,
            'notes' => 'Запрошен возврат от ' . trim($requestingVendor->f_name . ' ' . $requestingVendor->l_name),
        ]);

        // Create receipt items for refund receipt with refunded quantities
        foreach ($refundItems as $refundItem) {
            ReceiptItem::create([
                'receipt_id' => $refundReceipt->id,
                'product_id' => $refundItem['receipt_item']->product_id,
                'product_variation_id' => $refundItem['receipt_item']->product_variation_id,
                'quantity' => $refundItem['quantity'],
                'unit_price' => $refundItem['unit_price'],
                'total_price' => $refundItem['total_price'],
                'notes' => $refundItem['receipt_item']->notes,
            ]);
        }
        
        // Mark original receipt as COMPLETED if it was PENDING
        // When refund is requested, the inspection/decision action is completed
        if ($receipt->status === ReceiptStatusEnum::PENDING) {
            $receipt->status = ReceiptStatusEnum::COMPLETED;
            if (!$receipt->received_at) {
                $receipt->received_at = now();
            }
            $receipt->save();
        }
    }
}

