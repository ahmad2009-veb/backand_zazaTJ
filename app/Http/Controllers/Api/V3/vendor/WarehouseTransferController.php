<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Enums\WarehouseTransferType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseTransferRequest;
use App\Http\Requests\UpdateWarehouseTransferRequest;
use App\Http\Resources\WarehouseTransferResource;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\ProductVariation;
use App\Models\Vendor;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use App\Models\Counterparty;
use App\Models\OrderInstallment;
use App\Enums\TransactionTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Http\Traits\VendorEmployeeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseTransferController extends Controller
{
    use VendorEmployeeAccess;

    /**
     * Get all warehouse transfers for the vendor
     */
    public function index(Request $request)
    {
        $vendor = $this->getActingVendor();

        $query = WarehouseTransfer::forVendor($vendor->id);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by from_warehouse if provided
        if ($request->has('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->from_warehouse_id);
        }

        // Filter by to_warehouse if provided
        if ($request->has('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->to_warehouse_id);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $transfers = $query->with(['fromWarehouse', 'toWarehouse', 'items.product', 'items.productVariation'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return WarehouseTransferResource::collection($transfers);
    }

    /**
     * Create a new warehouse transfer
     */
    public function store(StoreWarehouseTransferRequest $request)
    {
        $vendor = $this->getActingVendor();
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            // Generate transfer number
            $transferNumber = 'TRF-' . date('YmdHis') . '-' . $vendor->id;

            $isExternal = $validated['transfer_type'] === 'external';

            // For external transfers, validate that to_vendor is not the same vendor
            if ($isExternal && isset($validated['to_vendor_id']) && $validated['to_vendor_id'] == $vendor->id) {
                return response()->json([
                    'message' => 'Нельзя создать внешнее перемещение самому себе'
                ], 422);
            }

            // Create transfer
            $transfer = WarehouseTransfer::create([
                'vendor_id' => $vendor->id,
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'] ?? null,
                'to_vendor_id' => $isExternal ? ($validated['to_vendor_id'] ?? null) : null,
                'transfer_number' => $transferNumber,
                'name' => $validated['name'] ?? null,
                'transfer_type' => $validated['transfer_type'],
                'status' => 'pending',
                'is_installment' => $isExternal && isset($validated['is_installment']) && $validated['is_installment'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Calculate total amount and process each product
            $totalAmount = 0;
            foreach ($validated['products'] as $productData) {
                $productId = $productData['product_id'];
                $product = Product::find($productId);

                // Check if product has variations array or is legacy format
                if (!empty($productData['variations']) && is_array($productData['variations'])) {
                    // New format with variations
                    foreach ($productData['variations'] as $variationData) {
                        // Skip items with quantity 0 or less
                        if (($variationData['quantity'] ?? 0) <= 0) {
                            continue;
                        }

                        // Look up product_variation_id from variation_id if provided
                        $productVariationId = null;
                        $sourceVariation = null;
                        if (!empty($variationData['variation_id'])) {
                            $sourceVariation = ProductVariation::where('product_id', $productId)
                                ->where('variation_id', $variationData['variation_id'])
                                ->first();
                            $productVariationId = $sourceVariation?->id;
                        }

                        // Get sale_price from request, or from variation, or from product
                        $salePrice = $variationData['sale_price'] 
                            ?? $sourceVariation?->sale_price 
                            ?? $sourceVariation?->price 
                            ?? $product?->price 
                            ?? 0;

                        WarehouseTransferItem::create([
                            'warehouse_transfer_id' => $transfer->id,
                            'product_id' => $productId,
                            'product_variation_id' => $productVariationId,
                            'quantity' => $variationData['quantity'],
                            'sale_price' => $salePrice,
                            'notes' => $variationData['notes'] ?? null,
                        ]);

                        $totalAmount += $salePrice * $variationData['quantity'];
                    }
                } else {
                    // Legacy format without variations
                    $quantity = $productData['quantity'] ?? 0;
                    if ($quantity <= 0) {
                        continue;
                    }

                    // Get sale_price from request
                    $salePrice = $productData['sale_price'] ?? $product?->price ?? 0;

                    WarehouseTransferItem::create([
                        'warehouse_transfer_id' => $transfer->id,
                        'product_id' => $productId,
                        'product_variation_id' => null,
                        'quantity' => $quantity,
                        'sale_price' => $salePrice,
                        'notes' => null,
                    ]);

                    $totalAmount += $salePrice * $quantity;
                }
            }

            // For external transfers, create a receipt (outgoing/sale to vendor)
            $receipt = null;
            if ($isExternal && isset($validated['to_vendor_id'])) {
                $receipt = $this->createTransferReceipt($transfer, $vendor, $validated['to_vendor_id'], $totalAmount);
                
                // Ensure counterparty exists for recipient vendor (Vendor 2 should have Vendor 1 as supplier)
                $this->ensureCounterpartyForTransfer($validated['to_vendor_id'], $vendor->id);
                
                // Create installment record with status='pending' if is_installment is true
                // Status will change to 'completed' when transfer status becomes 'sent'
                $isInstallment = isset($validated['is_installment']) && $validated['is_installment'];
                if ($isInstallment) {
                    OrderInstallment::create([
                        'external_transfer_id' => $transfer->id,
                        'order_id' => null, // Null for external transfers
                        'initial_payment' => isset($validated['initial_payment']) ? round((float)$validated['initial_payment'], 2) : 0,
                        'total_due' => isset($validated['total_due']) ? round((float)$validated['total_due'], 2) : 0,
                        'remaining_balance' => isset($validated['remaining_balance']) ? round((float)$validated['remaining_balance'], 2) : 0,
                        'due_date' => $validated['due_date'] ?? null,
                        'is_paid' => false,
                        'status' => 'pending', // Will change to 'completed' when transfer status becomes 'sent'
                        'notes' => $validated['notes'] ?? null,
                        'created_by' => $vendor->id,
                    ]);
                }
                
                // NOTE: Wallet processing will happen when status changes to "sent"
            }

            $transfer->load(['fromWarehouse', 'toWarehouse', 'toVendor', 'items.product', 'items.productVariation', 'receipt', 'installment']);

            DB::commit();

            return response()->json([
                'message' => 'Перемещение товаров успешно создано',
                'data' => new WarehouseTransferResource($transfer)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при создании перемещения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a receipt for external warehouse transfer
     */
    private function createTransferReceipt(WarehouseTransfer $transfer, $vendor, $recipientVendorId, $totalAmount)
    {
        // Generate receipt number
        $receiptNumber = 'RCP-TRF-' . date('YmdHis') . '-' . $vendor->id;

        // Use transfer name for receipt, fallback to transfer number
        $transferName = $transfer->name ?? $transfer->transfer_number;

        // Create the receipt (outgoing/sale receipt for the sender)
        $receipt = Receipt::create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $transfer->from_warehouse_id,
            'counterparty_id' => null, // No counterparty - it's a vendor transfer
            'recipient_vendor_id' => $recipientVendorId,
            'warehouse_transfer_id' => $transfer->id,
            'receipt_number' => $receiptNumber,
            'name' => 'Перемещение: ' . $transferName,
            'status' => 'pending', // Will be completed when transfer is sent
            'total_amount' => $totalAmount,
            'notes' => $transfer->notes,
        ]);

        // Create receipt items from transfer items
        $transfer->load('items');
        foreach ($transfer->items as $item) {
            $product = Product::find($item->product_id);
            $unitPrice = $product?->price ?? 0;

            ReceiptItem::create([
                'receipt_id' => $receipt->id,
                'product_id' => $item->product_id,
                'product_variation_id' => $item->product_variation_id,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $item->quantity,
                'notes' => $item->notes,
            ]);
        }

        return $receipt;
    }

    /**
     * Ensure counterparty exists for recipient vendor when transfer is created
     * When Vendor 1 sends transfer to Vendor 2, Vendor 2 should have Vendor 1 as a supplier
     */
    private function ensureCounterpartyForTransfer($recipientVendorId, $senderVendorId): void
    {
        // Check if counterparty already exists
        $existingCounterparty = Counterparty::where('vendor_id', $recipientVendorId)
            ->where('vendor_reference_id', $senderVendorId)
            ->where('type', 'supplier')
            ->first();

        if (!$existingCounterparty) {
            // Get sender vendor with store
            $senderVendor = Vendor::with('store')->find($senderVendorId);
            if (!$senderVendor) {
                return;
            }

            $store = $senderVendor->store;
            
            // Create counterparty for recipient vendor that references sender vendor
            Counterparty::create([
                'vendor_id' => $recipientVendorId,
                'vendor_reference_id' => $senderVendorId,
                'counterparty' => $senderVendor->f_name . ' ' . ($senderVendor->l_name ?? ''),
                'name' => $store ? $store->name : ($senderVendor->f_name . ' ' . ($senderVendor->l_name ?? '')),
                'address' => $store ? $store->address : null,
                'phone' => $senderVendor->phone,
                'type' => 'supplier',
                'status' => 'active',
                'balance' => 0,
            ]);
        }
    }

    /**
     * Create transfer installment
     */
    private function createTransferInstallment(WarehouseTransfer $transfer, $vendor, array $installmentData): void
    {
        $initialPayment = isset($installmentData['initial_payment']) ? (float)$installmentData['initial_payment'] : 0;
        $totalDue = isset($installmentData['total_due']) ? (float)$installmentData['total_due'] : 0;
        $remainingBalance = isset($installmentData['remaining_balance']) ? (float)$installmentData['remaining_balance'] : 0;
        $dueDate = isset($installmentData['due_date']) ? $installmentData['due_date'] : null;

        OrderInstallment::create([
            'external_transfer_id' => $transfer->id,
            'order_id' => null, // Null for external transfers
            'initial_payment' => round($initialPayment, 2),
            'total_due' => round($totalDue, 2),
            'remaining_balance' => round($remainingBalance, 2),
            'due_date' => $dueDate,
            'is_paid' => false,
            'created_by' => $vendor->id,
            'notes' => $installmentData['notes'] ?? null,
        ]);
    }

    /**
     * Update or create transfer installment
     */
    private function updateOrCreateTransferInstallment(WarehouseTransfer $transfer, $vendor, array $installmentData): void
    {
        $initialPayment = isset($installmentData['initial_payment']) ? (float)$installmentData['initial_payment'] : 0;
        $totalDue = isset($installmentData['total_due']) ? (float)$installmentData['total_due'] : 0;
        $remainingBalance = isset($installmentData['remaining_balance']) ? (float)$installmentData['remaining_balance'] : 0;
        $dueDate = isset($installmentData['due_date']) ? $installmentData['due_date'] : null;

        $installment = OrderInstallment::where('external_transfer_id', $transfer->id)->first();
        
        if ($installment) {
            // Update existing installment
            $installment->update([
                'initial_payment' => round($initialPayment, 2),
                'total_due' => round($totalDue, 2),
                'remaining_balance' => round($remainingBalance, 2),
                'due_date' => $dueDate,
                'notes' => $installmentData['notes'] ?? $installment->notes,
            ]);
        } else {
            // Create new installment
            OrderInstallment::create([
                'external_transfer_id' => $transfer->id,
                'order_id' => null, // Null for external transfers
                'initial_payment' => round($initialPayment, 2),
                'total_due' => round($totalDue, 2),
                'remaining_balance' => round($remainingBalance, 2),
                'due_date' => $dueDate,
                'is_paid' => false,
                'created_by' => $vendor->id,
                'notes' => $installmentData['notes'] ?? null,
            ]);
        }
    }

    /**
     * Clear existing wallet transactions for transfer update
     * Mark them as failed instead of deleting to maintain audit trail
     */
    private function clearExistingWalletTransactionsForTransfer(WarehouseTransfer $transfer): void
    {
        if (!$transfer->receipt) {
            return;
        }

        VendorWalletTransaction::where('receipt_id', $transfer->receipt->id)
            ->whereIn('status', ['pending', 'success'])
            ->update([
                'status' => 'failed',
                'meta' => DB::raw("JSON_SET(COALESCE(meta, '{}'), '$.superseded_reason', 'transfer_update', '$.superseded_at', NOW())")
            ]);
    }

    /**
     * Process wallets for external warehouse transfer
     */
    private function processWalletsForTransfer(Request $request, $vendor, Receipt $receipt, float $totalAmount, bool $isInstallment = false, array $validated = []): void
    {
        $vendorId = $vendor->id;
        $wallets = $request->input('wallets', []);

        if (!is_array($wallets) || empty($wallets)) {
            return;
        }

        // Validate wallet amounts against total amount
        $sum = 0.0;
        foreach ($wallets as $entry) {
            $amt = isset($entry['amount']) ? (float)$entry['amount'] : 0.0;
            if ($amt > 0) {
                $sum += $amt;
            }
        }

        // Validate that sum doesn't exceed total amount
        if (($sum - $totalAmount) > 0.01) {
            throw new \Exception("Сумма на кошельке превышает общую сумму перемещения. Сумма: {$sum}, Общая сумма: {$totalAmount}");
        }

        // For installments, validate against initial_payment (not total_due)
        // The remaining balance will be paid later as installment
        if ($isInstallment) {
            $initialPayment = isset($validated['initial_payment']) ? (float)$validated['initial_payment'] : 0;
            // For installments, wallet sum should equal initial_payment
            if (abs($sum - $initialPayment) > 0.01) {
                throw new \Exception("Сумма в кошельке должна быть равна первоначальному взносу для рассрочки. Сумма: {$sum}, Первоначальный взнос: {$initialPayment}");
            }
        } else {
            // For non-installment transfers, the sum must equal total amount
            if (abs($sum - $totalAmount) > 0.01) {
                throw new \Exception("Сумма в кошельке должна быть равна общей сумме перемещения для перемещений без рассрочки. Сумма: {$sum}, Общая сумма: {$totalAmount}");
            }
        }

        $initialPayment = $isInstallment ? (isset($validated['initial_payment']) ? (float)$validated['initial_payment'] : 0) : 0;

        // Process each wallet
        foreach ($wallets as $entry) {
            $amount = isset($entry['amount']) ? (float) $entry['amount'] : 0.0;
            if ($amount <= 0) {
                continue;
            }

            $vw = null;
            if (!empty($entry['vendor_wallet_id'])) {
                $vw = VendorWallet::where('id', (int) $entry['vendor_wallet_id'])
                    ->where('vendor_id', $vendorId)
                    ->first();
            } elseif (!empty($entry['id'])) {
                $vw = VendorWallet::firstOrCreate(
                    ['vendor_id' => $vendorId, 'wallet_id' => (int) $entry['id']],
                    ['is_enabled' => true]
                );
            } elseif (!empty($entry['wallet_id'])) {
                $vw = VendorWallet::firstOrCreate(
                    ['vendor_id' => $vendorId, 'wallet_id' => (int) $entry['wallet_id']],
                    ['is_enabled' => true]
                );
            }

            if (!$vw) {
                continue;
            }

            if ($isInstallment && $initialPayment > 0) {
                // For installment transfers, split the wallet transaction
                $initialPortion = min($amount, $initialPayment);
                $remainingPortion = $amount - $initialPortion;

                // Create transaction for initial payment (prepayment)
                if ($initialPortion > 0) {
                    // Find or create the "Реализация" category for this vendor
                    $category = TransactionCategory::firstOrCreate(
                        ['vendor_id' => $vendorId, 'name' => 'Реализация'],
                        ['parent_id' => 0]
                    );

                    // Create Transaction record for initial payment
                    $initialTransaction = Transaction::create([
                        'name' => 'Первоначальный взнос по перемещению #' . $receipt->warehouse_transfer_id,
                        'amount' => round($initialPortion, 2),
                        'transaction_category_id' => $category->id,
                        'description' => 'Первоначальный взнос по рассрочке перемещения #' . $receipt->warehouse_transfer_id,
                        'type' => TransactionTypeEnum::INCOME,
                        'vendor_id' => $vendorId,
                        'status' => TransactionStatusEnum::SUCCESS
                    ]);

                    // Create VendorWalletTransaction linked to the Transaction
                    VendorWalletTransaction::create([
                        'vendor_id' => $vendorId,
                        'vendor_wallet_id' => $vw->id,
                        'receipt_id' => $receipt->id,
                        'transaction_id' => $initialTransaction->id,
                        'amount' => round($initialPortion, 2),
                        'status' => 'success',
                        'paid_at' => now(),
                        'meta' => [
                            'source' => 'warehouse_transfer',
                            'transfer_type' => 'external',
                            'payment_type' => 'initial_payment'
                        ]
                    ]);
                    $initialPayment -= $initialPortion; // Reduce remaining initial payment
                }

                // Create transaction for remaining amount (pending until transfer success)
                if ($remainingPortion > 0) {
                    VendorWalletTransaction::create([
                        'vendor_id' => $vendorId,
                        'vendor_wallet_id' => $vw->id,
                        'receipt_id' => $receipt->id,
                        'amount' => round($remainingPortion, 2),
                        'status' => 'pending',
                        'meta' => [
                            'source' => 'warehouse_transfer',
                            'transfer_type' => 'external',
                            'payment_type' => 'remaining_balance'
                        ]
                    ]);
                }
            } else {
                // For non-installment transfers, create single pending transaction
                VendorWalletTransaction::create([
                    'vendor_id' => $vendorId,
                    'vendor_wallet_id' => $vw->id,
                    'receipt_id' => $receipt->id,
                    'amount' => round($amount, 2),
                    'status' => 'pending',
                    'meta' => [
                        'source' => 'warehouse_transfer',
                        'transfer_type' => 'external'
                    ]
                ]);
            }
        }
    }

    /**
     * Get a single warehouse transfer
     */
    public function show(WarehouseTransfer $transfer)
    {
        $vendor = $this->getActingVendor();

        // Allow access if vendor is sender OR receiver
        if ($transfer->vendor_id !== $vendor->id && $transfer->to_vendor_id !== $vendor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transfer->load(['fromWarehouse', 'toWarehouse', 'toVendor', 'vendor', 'items.product', 'items.productVariation']);

        return new WarehouseTransferResource($transfer);
    }

    /**
     * Update warehouse transfer status or items
     */
    public function update(UpdateWarehouseTransferRequest $request, WarehouseTransfer $transfer)
    {
        $vendor = $this->getActingVendor();

        if ($transfer->vendor_id !== $vendor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();
        // Also get raw input to ensure we capture all installment data even if validation filters it
        $rawInput = $request->all();

        DB::beginTransaction();
        try {
            $oldStatus = $transfer->status;
            $newStatus = $validated['status'] ?? $oldStatus;

            // Allow edits when pending or sent, block when completed/cancelled (accepted by receiver)
            $canEdit = in_array($oldStatus, ['pending', 'sent']);

            // Handle transfer type change (only when pending or sent)
            if (isset($validated['transfer_type'])) {
                if (!$canEdit) {
                    return response()->json([
                        'message' => 'Нельзя изменять тип перемещения после принятия или отмены'
                    ], 422);
                }

                $newType = $validated['transfer_type'];
                $oldType = $transfer->transfer_type->value ?? $transfer->transfer_type;

                if ($newType !== $oldType) {
                    // Changing from internal to external
                    if ($newType === 'external') {
                        if (!isset($validated['to_vendor_id'])) {
                            return response()->json([
                                'message' => 'Целевой вендор обязателен для внешнего перемещения'
                            ], 422);
                        }
                        if ($validated['to_vendor_id'] == $vendor->id) {
                            return response()->json([
                                'message' => 'Нельзя создать внешнее перемещение самому себе'
                            ], 422);
                        }
                        $transfer->update([
                            'transfer_type' => 'external',
                            'to_vendor_id' => $validated['to_vendor_id'],
                            'to_warehouse_id' => null, // Clear warehouse, receiver will choose
                        ]);
                        
                        // Ensure counterparty exists for recipient vendor
                        $this->ensureCounterpartyForTransfer($validated['to_vendor_id'], $vendor->id);
                    }
                    // Changing from external to internal
                    else {
                        if (!isset($validated['to_warehouse_id'])) {
                            return response()->json([
                                'message' => 'Целевой склад обязателен для внутреннего перемещения'
                            ], 422);
                        }
                        $transfer->update([
                            'transfer_type' => 'internal',
                            'to_warehouse_id' => $validated['to_warehouse_id'],
                            'to_vendor_id' => null,
                        ]);
                    }
                }
            }

            // Update basic fields (only when pending)
            $updateData = [];

            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }
            if (isset($validated['notes'])) {
                $updateData['notes'] = $validated['notes'];
            }
            
            // Update is_installment flag (only for external transfers when pending)
            if (isset($validated['is_installment']) && $canEdit && $transfer->isExternal()) {
                $updateData['is_installment'] = $validated['is_installment'];
            }

            // Update from_warehouse_id (only when pending or sent)
            if (isset($validated['from_warehouse_id']) && $canEdit) {
                $updateData['from_warehouse_id'] = $validated['from_warehouse_id'];
            }

            // Update to_warehouse_id for internal transfers (only when pending or sent)
            if (isset($validated['to_warehouse_id']) && $canEdit && !$transfer->isExternal()) {
                if ($validated['to_warehouse_id'] == $transfer->from_warehouse_id) {
                    return response()->json([
                        'message' => 'Исходный и целевой склады должны быть разными'
                    ], 422);
                }
                $updateData['to_warehouse_id'] = $validated['to_warehouse_id'];
            }

            // Update to_vendor_id for external transfers (only when pending or sent)
            if (isset($validated['to_vendor_id']) && $canEdit && $transfer->isExternal()) {
                if ($validated['to_vendor_id'] == $vendor->id) {
                    return response()->json([
                        'message' => 'Нельзя создать внешнее перемещение самому себе'
                    ], 422);
                }
                $updateData['to_vendor_id'] = $validated['to_vendor_id'];
            }

            if (!empty($updateData)) {
                $transfer->update($updateData);
                
                // If to_vendor_id was updated, ensure counterparty exists
                if (isset($updateData['to_vendor_id']) && $updateData['to_vendor_id']) {
                    $this->ensureCounterpartyForTransfer($updateData['to_vendor_id'], $vendor->id);
                }
            }

            // Update items if provided (only allowed when pending or sent)
            $totalAmount = null;
            if (isset($validated['products']) && is_array($validated['products'])) {
                if (!$canEdit) {
                    return response()->json([
                        'message' => 'Нельзя изменять товары после принятия или отмены перемещения'
                    ], 422);
                }

                // Delete existing items
                WarehouseTransferItem::where('warehouse_transfer_id', $transfer->id)->delete();

                // Recalculate total amount
                $totalAmount = 0;

                // Create new items - supports both variations and legacy formats
                foreach ($validated['products'] as $productData) {
                    $productId = $productData['product_id'];
                    $product = Product::find($productId);

                    // Check if product has variations array or is legacy format
                    if (!empty($productData['variations']) && is_array($productData['variations'])) {
                        // New format with variations
                        foreach ($productData['variations'] as $variationData) {
                            // Skip items with quantity 0 or less
                            if (($variationData['quantity'] ?? 0) <= 0) {
                                continue;
                            }

                            // Look up product_variation_id from variation_id if provided
                            $productVariationId = null;
                            $sourceVariation = null;
                            if (!empty($variationData['variation_id'])) {
                                $sourceVariation = ProductVariation::where('product_id', $productId)
                                    ->where('variation_id', $variationData['variation_id'])
                                    ->first();
                                $productVariationId = $sourceVariation?->id;
                            }

                            // Get sale_price from request, or from variation, or from product
                            $salePrice = $variationData['sale_price'] 
                                ?? $sourceVariation?->sale_price 
                                ?? $sourceVariation?->price 
                                ?? $product?->price 
                                ?? 0;

                            WarehouseTransferItem::create([
                                'warehouse_transfer_id' => $transfer->id,
                                'product_id' => $productId,
                                'product_variation_id' => $productVariationId,
                                'quantity' => $variationData['quantity'],
                                'sale_price' => $salePrice,
                                'notes' => $variationData['notes'] ?? null,
                            ]);

                            $totalAmount += $salePrice * $variationData['quantity'];
                        }
                    } else {
                        // Legacy format without variations
                        $quantity = $productData['quantity'] ?? 0;
                        if ($quantity <= 0) {
                            continue;
                        }

                        // Get sale_price from request
                        $salePrice = $productData['sale_price'] ?? $product?->price ?? 0;

                        WarehouseTransferItem::create([
                            'warehouse_transfer_id' => $transfer->id,
                            'product_id' => $productId,
                            'product_variation_id' => null,
                            'quantity' => $quantity,
                            'sale_price' => $salePrice,
                            'notes' => null,
                        ]);

                        $totalAmount += $salePrice * $quantity;
                    }
                }

                // Update receipt total_amount if it exists
                if ($transfer->receipt && $totalAmount !== null) {
                    $transfer->receipt->update(['total_amount' => $totalAmount]);
                }
            }

            // Refresh to get updated transfer_type
            $transfer->refresh();
            $isExternal = $transfer->isExternal();

            // Handle status changes with inventory movements
            if ($newStatus !== $oldStatus) {
                // For EXTERNAL transfers: when status changes to "sent", transfer ownership
                if ($isExternal && $newStatus === 'sent' && $oldStatus === 'pending') {
                    // Transfer product ownership to recipient vendor
                    $this->handleExternalTransferSent($transfer);

                    $transfer->update([
                        'status' => 'sent',
                        'transferred_at' => now()
                    ]);

                    // Create receipt for external transfer if not exists
                    if (!$transfer->receipt && $transfer->to_vendor_id) {
                        $transfer->load('items.product');
                        // Calculate total amount
                        $totalAmount = 0;
                        foreach ($transfer->items as $item) {
                            if ($item->product) {
                                $totalAmount += $item->product->price * $item->quantity;
                            }
                        }
                        $this->createTransferReceipt($transfer, $vendor, $transfer->to_vendor_id, $totalAmount);
                        $transfer->refresh();
                    }

                    // Now that transfer is approved (sent), create installment and process wallets
                    if ($transfer->receipt) {
                        // Update receipt status to completed when transfer is sent
                        $transfer->receipt->update([
                            'status' => 'completed',
                            'received_at' => now(),
                        ]);

                        // Update installment status to 'completed' when transfer status becomes 'sent'
                        $isInstallment = $transfer->is_installment ?? false;
                        if ($isInstallment && $transfer->installment) {
                            // Update installment status from 'pending' to 'completed'
                            $transfer->installment->update(['status' => 'completed']);
                            
                            // If new installment data is provided in the update request, update it
                            $hasNewInstallmentData = array_key_exists('initial_payment', $validated) || 
                                                    array_key_exists('total_due', $validated) || 
                                                    array_key_exists('remaining_balance', $validated) ||
                                                    array_key_exists('due_date', $validated) ||
                                                    array_key_exists('initial_payment', $rawInput) || 
                                                    array_key_exists('total_due', $rawInput) || 
                                                    array_key_exists('remaining_balance', $rawInput) ||
                                                    array_key_exists('due_date', $rawInput);
                            
                            if ($hasNewInstallmentData) {
                                // Use new data from request if provided
                                $getInstallmentValue = function($key, $default = null) use ($validated, $rawInput, $transfer) {
                                    if (array_key_exists($key, $validated)) {
                                        $val = $validated[$key];
                                        return $val !== null ? $val : $default;
                                    }
                                    if (array_key_exists($key, $rawInput)) {
                                        $val = $rawInput[$key];
                                        return $val !== null ? $val : $default;
                                    }
                                    return $default;
                                };
                                
                                $installmentData = [
                                    'initial_payment' => (float)$getInstallmentValue('initial_payment', $transfer->installment->initial_payment ?? 0),
                                    'total_due' => (float)$getInstallmentValue('total_due', $transfer->installment->total_due ?? 0),
                                    'remaining_balance' => (float)$getInstallmentValue('remaining_balance', $transfer->installment->remaining_balance ?? 0),
                                    'due_date' => $getInstallmentValue('due_date', $transfer->installment->due_date ?? null),
                                    'notes' => $getInstallmentValue('notes', $transfer->installment->notes ?? null),
                                ];
                                
                                $transfer->installment->update($installmentData);
                            }
                            $transfer->refresh();
                        }

                        // Process wallets if provided (only when status becomes "sent")
                        if (!empty($validated['wallets']) && is_array($validated['wallets'])) {
                            $transfer->load('items');
                            $totalAmount = 0;
                            foreach ($transfer->items as $item) {
                                $totalAmount += ($item->sale_price ?? 0) * $item->quantity;
                            }
                            
                            // Use installment data for wallet processing
                            $walletInstallmentData = [];
                            
                            // Check if new installment data is in request
                            $hasNewData = array_key_exists('initial_payment', $validated) || 
                                         array_key_exists('total_due', $validated) || 
                                         array_key_exists('initial_payment', $rawInput) || 
                                         array_key_exists('total_due', $rawInput);
                            
                            if ($hasNewData) {
                                // Use data from request (validated or raw input)
                                $walletInstallmentData = [
                                    'initial_payment' => (float)($validated['initial_payment'] ?? $rawInput['initial_payment'] ?? 0),
                                    'total_due' => (float)($validated['total_due'] ?? $rawInput['total_due'] ?? 0),
                                    'remaining_balance' => (float)($validated['remaining_balance'] ?? $rawInput['remaining_balance'] ?? 0),
                                    'due_date' => $validated['due_date'] ?? $rawInput['due_date'] ?? null,
                                    'notes' => $validated['notes'] ?? $rawInput['notes'] ?? null,
                                ];
                            } elseif ($transfer->installment) {
                                // Use existing installment record
                                $walletInstallmentData = [
                                    'initial_payment' => $transfer->installment->initial_payment,
                                    'total_due' => $transfer->installment->total_due,
                                    'remaining_balance' => $transfer->installment->remaining_balance,
                                    'due_date' => $transfer->installment->due_date,
                                    'notes' => $transfer->installment->notes ?? null,
                                ];
                            }
                            
                            $this->processWalletsForTransfer($request, $vendor, $transfer->receipt, $totalAmount, $isInstallment, $walletInstallmentData);
                        }
                    }
                }
                // For INTERNAL transfers: when status changes to "completed", move inventory
                elseif (!$isExternal && $newStatus === 'completed' && $oldStatus !== 'completed') {
                    $transfer->load('items.product.variations');

                    foreach ($transfer->items as $item) {
                        $this->handleInternalTransferItem($transfer, $item);
                    }

                    $transfer->update([
                        'status' => 'completed',
                        'transferred_at' => now()
                    ]);
                } else {
                    // Just update status for other cases (cancelled, etc.)
                    $transfer->update(['status' => $newStatus]);
                }
            }

            // If transfer type changed to external, create receipt if not exists
            if ($isExternal && !$transfer->receipt && $transfer->to_vendor_id) {
                $transfer->load('items');
                if ($totalAmount === null) {
                    $totalAmount = 0;
                    foreach ($transfer->items as $item) {
                        $totalAmount += ($item->sale_price ?? 0) * $item->quantity;
                    }
                }
                $this->createTransferReceipt($transfer, $vendor, $transfer->to_vendor_id, $totalAmount);
            }

            // Handle installment update/create (only for external transfers when pending)
            if ($isExternal && $canEdit && isset($validated['is_installment'])) {
                $isInstallment = $validated['is_installment'];
                $updateData['is_installment'] = $isInstallment;
                
                if ($isInstallment) {
                    // Update or create installment record
                    $this->updateOrCreateTransferInstallment($transfer, $vendor, $validated);
                } else {
                    // Remove installment if exists
                    if ($transfer->installment) {
                        $transfer->installment->delete();
                    }
                }
            } elseif ($isExternal && $canEdit && $transfer->is_installment) {
                // If installment data is being updated (but is_installment flag is not being changed)
                $hasInstallmentData = isset($validated['initial_payment']) || 
                                     isset($validated['total_due']) || 
                                     isset($validated['remaining_balance']) ||
                                     isset($validated['due_date']);
                
                if ($hasInstallmentData && $transfer->installment) {
                    $this->updateOrCreateTransferInstallment($transfer, $vendor, $validated);
                }
            }

            // Handle wallet updates (only for external transfers when pending)
            if ($isExternal && $canEdit && !empty($validated['wallets']) && is_array($validated['wallets'])) {
                // Ensure receipt exists
                if (!$transfer->receipt && $transfer->to_vendor_id) {
                    if ($totalAmount === null) {
                        $transfer->load('items');
                        $totalAmount = 0;
                        foreach ($transfer->items as $item) {
                            $totalAmount += ($item->sale_price ?? 0) * $item->quantity;
                        }
                    }
                    $this->createTransferReceipt($transfer, $vendor, $transfer->to_vendor_id, $totalAmount);
                    $transfer->refresh();
                }
                
                if ($transfer->receipt) {
                    // Clear existing wallet transactions
                    $this->clearExistingWalletTransactionsForTransfer($transfer);
                    
                    // Get total amount for validation
                    if ($totalAmount === null) {
                        $transfer->load('items');
                        $totalAmount = 0;
                        foreach ($transfer->items as $item) {
                            $totalAmount += ($item->sale_price ?? 0) * $item->quantity;
                        }
                    }
                    
                    // Process new wallet transactions
                    $isInstallment = $transfer->is_installment ?? false;
                    $this->processWalletsForTransfer($request, $vendor, $transfer->receipt, $totalAmount, $isInstallment, $validated);
                }
            }

            $transfer->load(['fromWarehouse', 'toWarehouse', 'toVendor', 'items.product', 'items.productVariation', 'receipt', 'installment']);

            DB::commit();

            return response()->json([
                'message' => 'Перемещение товаров успешно обновлено',
                'data' => new WarehouseTransferResource($transfer)
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при обновлении перемещения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available transfer types
     */
    public function transferTypes()
    {
        return response()->json([
            'data' => WarehouseTransferType::toArray()
        ]);
    }

    /**
     * Get all available transfer statuses
     */
    public function statuses(Request $request)
    {
        $type = $request->get('type', 'internal');
        $role = $request->get('role', 'sender'); // sender or receiver

        if ($type === 'external') {
            if ($role === 'receiver') {
                // Statuses for incoming transfers (receiver's view)
                return response()->json([
                    'data' => [
                        ['value' => 'sent', 'label' => 'Ожидает принятия'],
                        ['value' => 'received', 'label' => 'Принято'],
                        ['value' => 'cancelled', 'label' => 'Отменено'],
                    ]
                ]);
            }

            // Statuses for outgoing transfers (sender's view)
            return response()->json([
                'data' => [
                    ['value' => 'pending', 'label' => 'Черновик'],
                    ['value' => 'sent', 'label' => 'Отправлено'],
                    ['value' => 'received', 'label' => 'Доставлено'],
                    ['value' => 'cancelled', 'label' => 'Отменено'],
                ]
            ]);
        }

        // Internal transfer statuses
        return response()->json([
            'data' => [
                ['value' => 'pending', 'label' => 'Ожидает'],
                ['value' => 'completed', 'label' => 'Завершено'],
                ['value' => 'cancelled', 'label' => 'Отменено'],
            ]
        ]);
    }

    /**
     * Search vendors for external transfers
     */
    public function searchVendors(Request $request)
    {
        $vendor = $this->getActingVendor();
        $search = $request->get('search', '');

        if (strlen($search) < 2) {
            return response()->json([
                'data' => []
            ]);
        }

        $vendors = Vendor::where('id', '!=', $vendor->id)
            ->where(function ($query) use ($search) {
                // Check if search is numeric for ID search
                if (is_numeric($search)) {
                    $query->where('id', $search);
                }
                $query->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('f_name', 'like', "%{$search}%")
                    ->orWhere('l_name', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'f_name', 'l_name', 'phone']);

        return response()->json([
            'data' => $vendors->map(function ($v) {
                return [
                    'id' => $v->id,
                    'name' => trim($v->f_name . ' ' . $v->l_name),
                    'phone' => $v->phone,
                ];
            })
        ]);
    }

    /**
     * Get incoming transfers for this vendor (external transfers sent to us)
     */
    public function incoming(Request $request)
    {
        $vendor = $this->getActingVendor();

        $query = WarehouseTransfer::incomingForVendor($vendor->id);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        $perPage = $request->get('per_page', 15);
        $transfers = $query->with(['fromWarehouse', 'vendor', 'items.product', 'items.productVariation'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return WarehouseTransferResource::collection($transfers);
    }

    /**
     * Get count of incoming transfers for this vendor
     */
    public function incomingCount(Request $request)
    {
        $vendor = $this->getActingVendor();

        $query = WarehouseTransfer::incomingForVendor($vendor->id);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        $count = $query->count();

        return response()->json([
            'count' => $count
        ]);
    }

    // Old accept method removed - use acceptTransfer instead

    /**
     * Handle internal transfer item - either move or clone product
     */
    private function handleInternalTransferItem(WarehouseTransfer $transfer, WarehouseTransferItem $item): void
    {
        $product = $item->product;
        $transferQuantity = $item->quantity;

        // Get total available quantity from source
        $sourceQuantity = $this->getProductQuantityInWarehouse($product, $transfer->from_warehouse_id, $item->product_variation_id);

        if ($transferQuantity > $sourceQuantity) {
            throw new \Exception("Недостаточно товара '{$product->name}' на складе. Доступно: {$sourceQuantity}, требуется: {$transferQuantity}");
        }

        // Check if this is a full transfer (moving ALL quantity)
        $isFullTransfer = ($transferQuantity >= $sourceQuantity);

        if ($isFullTransfer) {
            // Full transfer - just update warehouse_id
            $product->warehouse_id = $transfer->to_warehouse_id;
            $product->save();
        } else {
            // Partial transfer - deduct from source and clone to destination
            $this->deductQuantityFromProduct($product, $transferQuantity, $item->product_variation_id);
            $this->cloneProductToWarehouse($product, $transfer->to_warehouse_id, $transferQuantity, $item->product_variation_id);
        }
    }

    /**
     * Get product quantity in a specific warehouse
     */
    private function getProductQuantityInWarehouse(Product $product, int $warehouseId, ?int $variationId = null): float
    {
        // If specific variation is requested
        if ($variationId) {
            $variation = ProductVariation::find($variationId);
            return $variation ? $variation->quantity : 0;
        }

        // Check if product has variations (use method call to get relationship, not column)
        $variations = $product->variations()->get();
        if ($variations->count() > 0) {
            return $variations->sum('quantity');
        }

        // Fallback to product quantity
        return $product->quantity ?? 0;
    }

    /**
     * Deduct quantity from product (for partial transfers)
     */
    private function deductQuantityFromProduct(Product $product, float $quantity, ?int $variationId = null): void
    {
        if ($variationId) {
            $variation = ProductVariation::find($variationId);
            if ($variation) {
                $variation->quantity -= $quantity;
                $variation->save();
            }
            return;
        }

        // Deduct from first variation or product quantity (use method call to get relationship)
        $variations = $product->variations()->get();
        if ($variations->count() > 0) {
            $defaultVariation = $variations->first();
            $defaultVariation->quantity -= $quantity;
            $defaultVariation->save();
        } else {
            $product->quantity -= $quantity;
            $product->save();
        }
    }

    /**
     * Clone product to destination warehouse (for partial transfers)
     */
    private function cloneProductToWarehouse(Product $sourceProduct, ?int $warehouseId, float $quantity, ?int $variationId = null): Product
    {
        // Clone the product
        $newProduct = $sourceProduct->replicate();
        $newProduct->warehouse_id = $warehouseId; // Can be null for external transfers (in transit)
        $newProduct->quantity = $quantity;
        $newProduct->save();

        // Clone variations (use method call to get relationship)
        $sourceVariations = $sourceProduct->variations()->get();

        if ($variationId) {
            // Clone only the specific variation with transferred quantity
            $sourceVariation = ProductVariation::find($variationId);
            if ($sourceVariation) {
                $newVariation = $sourceVariation->replicate();
                $newVariation->product_id = $newProduct->id;
                $newVariation->quantity = $quantity;
                $newVariation->save();
            }
        } elseif ($sourceVariations && $sourceVariations->count() > 0) {
            // Clone all variations, but only first one gets the transferred quantity
            $isFirst = true;
            foreach ($sourceVariations as $variation) {
                $newVariation = $variation->replicate();
                $newVariation->product_id = $newProduct->id;
                $newVariation->quantity = $isFirst ? $quantity : 0;
                $newVariation->save();
                $isFirst = false;
            }
        }

        return $newProduct;
    }

    /**
     * Handle external transfer - transfer ownership to recipient vendor
     */
    private function handleExternalTransferSent(WarehouseTransfer $transfer): void
    {
        // Get recipient vendor's store
        $recipientVendor = Vendor::find($transfer->to_vendor_id);
        if (!$recipientVendor) {
            throw new \Exception("Получатель не найден");
        }

        $recipientStore = $recipientVendor->store;
        if (!$recipientStore) {
            throw new \Exception("Магазин получателя не найден");
        }

        $transfer->load('items.product.variations');

        // Group items by product_id to handle multiple variations of the same product
        $itemsByProduct = [];
        foreach ($transfer->items as $item) {
            $productId = $item->product_id;
            if (!isset($itemsByProduct[$productId])) {
                $itemsByProduct[$productId] = [];
            }
            $itemsByProduct[$productId][] = $item;
        }

        // Collect sale products for creating sale record
        $saleProducts = [];
        $totalPrice = 0;
        $totalQuantity = 0;
        $productMapping = []; // Map old product_id to new product_id for updating transfer items

        // Process each product group
        foreach ($itemsByProduct as $productId => $items) {
            $product = $items[0]->product;
            $isFullTransfer = true;
            $hasPartialTransfer = false;

            // Check if all items are full transfers
            foreach ($items as $item) {
                $transferQuantity = $item->quantity;
                $sourceQuantity = $this->getProductQuantityInWarehouse($product, $transfer->from_warehouse_id, $item->product_variation_id);

                if ($transferQuantity > $sourceQuantity) {
                    throw new \Exception("Недостаточно товара '{$product->name}' на складе. Доступно: {$sourceQuantity}, требуется: {$transferQuantity}");
                }

                if ($transferQuantity < $sourceQuantity) {
                    $isFullTransfer = false;
                    $hasPartialTransfer = true;
                }
            }

            if ($isFullTransfer) {
                // Full transfer - move entire product to recipient vendor
                $product->store_id = $recipientStore->id;
                $product->warehouse_id = null; // In transit - no warehouse yet
                
                // Process each variation/item
                foreach ($items as $item) {
                    $transferQuantity = $item->quantity;
                    $salePrice = $item->sale_price ?? $product->price ?? 0;
                    $totalPrice += $salePrice * $transferQuantity;
                    $totalQuantity += $transferQuantity;

                    // Prepare sale product data
                    $saleProducts[] = [
                        'product_id' => $product->id,
                        'quantity' => $transferQuantity,
                        'price' => $salePrice,
                        'purchase_price' => $product->purchase_price ?? 0,
                        'variation' => $item->product_variation_id ? (string)$item->product_variation_id : null,
                        'discount' => 0,
                        'discount_type' => 'amount',
                    ];

                    // Update variation prices if this item has a specific variation
                    if ($item->product_variation_id) {
                        $variation = ProductVariation::find($item->product_variation_id);
                        if ($variation && $variation->product_id == $product->id) {
                            $variation->cost_price = $salePrice;
                            $variation->sale_price = $salePrice;
                            $variation->save();
                        }
                    }
                }

                // Set purchase_price for recipient (use first item's sale_price)
                $firstItem = $items[0];
                $avgSalePrice = $firstItem->sale_price ?? $product->price ?? 0;
                $product->purchase_price = $avgSalePrice;
                $product->price = $avgSalePrice;
                
                // Update product quantity to sum of all variations
                $product->quantity = $product->variations()->sum('quantity');
                $product->save();

                $productMapping[$productId] = $product->id;
            } else {
                // Partial transfer - clone product once, then add all variations
                $newProduct = null;
                $totalClonedQuantity = 0;

                // First, deduct all quantities from source
                foreach ($items as $item) {
                    $transferQuantity = $item->quantity;
                    $this->deductQuantityFromProduct($product, $transferQuantity, $item->product_variation_id);
                    $totalClonedQuantity += $transferQuantity;
                }

                // Clone product once (use first item for cloning, but we'll handle variations separately)
                $firstItem = $items[0];
                $newProduct = $this->cloneProductToWarehouse($product, null, 0, null); // Clone with 0 quantity initially
                $newProduct->store_id = $recipientStore->id;
                
                // Delete all cloned variations - we'll create them properly below
                $newProduct->variations()->delete();

                // Process each variation/item and add to the cloned product
                foreach ($items as $item) {
                    $transferQuantity = $item->quantity;
                    $salePrice = $item->sale_price ?? $product->price ?? 0;
                    $totalPrice += $salePrice * $transferQuantity;
                    $totalQuantity += $transferQuantity;

                    // Prepare sale product data
                    $saleProducts[] = [
                        'product_id' => $newProduct->id,
                        'quantity' => $transferQuantity,
                        'price' => $salePrice,
                        'purchase_price' => $product->purchase_price ?? 0,
                        'variation' => $item->product_variation_id ? (string)$item->product_variation_id : null,
                        'discount' => 0,
                        'discount_type' => 'amount',
                    ];

                    if ($item->product_variation_id) {
                        // Clone the specific variation with transferred quantity
                        $sourceVariation = ProductVariation::find($item->product_variation_id);
                        if ($sourceVariation) {
                            $clonedVariation = $sourceVariation->replicate();
                            $clonedVariation->product_id = $newProduct->id;
                            $clonedVariation->quantity = $transferQuantity;
                            $clonedVariation->cost_price = $salePrice;
                            $clonedVariation->sale_price = $salePrice;
                            $clonedVariation->save();
                        }
                    } else {
                        // No variation - create a default variation or update product quantity
                        $firstVariation = $newProduct->variations()->first();
                        if ($firstVariation) {
                            $firstVariation->quantity = $transferQuantity;
                            $firstVariation->cost_price = $salePrice;
                            $firstVariation->sale_price = $salePrice;
                            $firstVariation->save();
                        } else {
                            // No variations exist - set product quantity directly
                            $newProduct->quantity = $transferQuantity;
                        }
                    }

                    // Update transfer item to reference new product
                    $item->product_id = $newProduct->id;
                    $item->save();
                }

                // Set purchase_price for recipient (use average or first item's sale_price)
                $avgSalePrice = $items[0]->sale_price ?? $product->price ?? 0;
                $newProduct->purchase_price = $avgSalePrice;
                $newProduct->price = $avgSalePrice;
                
                // Update product quantity to sum of all variations
                if ($newProduct->variations()->count() > 0) {
                    $newProduct->quantity = $newProduct->variations()->sum('quantity');
                }
                $newProduct->save();

                $productMapping[$productId] = $newProduct->id;
            }
        }

        // Create pending sale for this external transfer
        $sale = Sale::create([
            'name' => 'Перемещение: ' . ($transfer->name ?? $transfer->transfer_number),
            'status' => 'pending',
            'warehouse_id' => $transfer->from_warehouse_id,
            'user_id' => null, // No customer - this is vendor-to-vendor
            'vendor_id' => $recipientVendor->id, // Recipient vendor as buyer
            'warehouse_transfer_id' => $transfer->id,
            'total_price' => $totalPrice,
            'products_count' => $totalQuantity,
            'delivery_man_id' => null,
            'delivery_charge' => 0,
        ]);

        // Create sale products
        foreach ($saleProducts as $saleProduct) {
            SaleProduct::create(array_merge($saleProduct, ['sale_id' => $sale->id]));
        }
    }

    /**
     * Handle external transfer acceptance - assign to recipient's warehouse
     */
    public function acceptTransfer(Request $request, WarehouseTransfer $transfer)
    {
        $vendor = $this->getActingVendor();

        // Verify this vendor is the recipient
        if ($transfer->to_vendor_id !== $vendor->id) {
            return response()->json([
                'message' => 'Вы не являетесь получателем этого перемещения'
            ], 403);
        }

        // Verify transfer is in "sent" status
        if ($transfer->status !== 'sent') {
            return response()->json([
                'message' => 'Перемещение не может быть принято в текущем статусе'
            ], 422);
        }

        $validated = $request->validate([
            'to_warehouse_id' => 'required|exists:warehouses,id'
        ], [
            'to_warehouse_id.required' => 'Склад получения обязателен',
            'to_warehouse_id.exists' => 'Склад не найден'
        ]);

        // Verify the warehouse belongs to this vendor
        $warehouse = $vendor->warehouses()->where('warehouses.id', $validated['to_warehouse_id'])->first();
        if (!$warehouse) {
            return response()->json([
                'message' => 'Склад не найден или не принадлежит вам'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $transfer->load('items.product.variations', 'items.productVariation');

            // Create receipt (Приход) for the recipient vendor
            // Products will be created/moved to warehouse when receipt is marked as completed
            $totalAmount = 0;
            $receiptItems = [];

            foreach ($transfer->items as $item) {
                $incomingProduct = $item->product;
                $transferQuantity = $item->quantity;
                $variationId = $item->product_variation_id;

                // Use sale_price from transfer item (what Vendor 1 is charging Vendor 2)
                // This becomes Vendor 2's purchase_price/cost_price
                // Fallback: if sale_price is 0 or null, get it from the variation or product
                $senderSalePrice = $item->sale_price;
                if (!$senderSalePrice || $senderSalePrice == 0) {
                    if ($variationId && $item->productVariation) {
                        $senderSalePrice = $item->productVariation->sale_price 
                            ?? $item->productVariation->price 
                            ?? $incomingProduct->price 
                            ?? 0;
                    } else {
                        $senderSalePrice = $incomingProduct->price ?? 0;
                    }
                }
                
                $itemTotal = $senderSalePrice * $transferQuantity;
                $totalAmount += $itemTotal;

                // Just create receipt items - products will be processed when receipt is completed
                // Store reference to the incoming product (which is in transit with warehouse_id = null)
                $receiptItems[] = [
                    'product_id' => $incomingProduct->id,
                    'product_variation_id' => $variationId,
                    'quantity' => $transferQuantity,
                    'unit_price' => $senderSalePrice,
                    'total_price' => $itemTotal,
                ];
            }

            $transfer->update([
                'status' => 'received',
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'received_at' => now()
            ]);

            // Mark the associated sale as completed and create transactions
            $sale = Sale::where('warehouse_transfer_id', $transfer->id)->first();
            $senderVendor = Vendor::find($transfer->vendor_id); // vendor_id is the sender

            if ($sale) {
                $sale->status = 'completed';
                $sale->save();

                // Create income transaction for the sender vendor only
                // Recipient vendor can create their own expense transaction manually if needed
                if ($senderVendor) {
                    // Find the sender's receipt for this transfer
                    $senderReceipt = Receipt::where('warehouse_transfer_id', $transfer->id)
                        ->where('vendor_id', $senderVendor->id)
                        ->first();

                    if ($senderReceipt) {
                        // Check if there's already a prepayment transaction for installments
                        $prepaymentTransaction = null;
                        if ($transfer->is_installment) {
                            $prepaymentWalletTx = VendorWalletTransaction::where('receipt_id', $senderReceipt->id)
                                ->where('vendor_id', $senderVendor->id)
                                ->whereNotNull('transaction_id')
                                ->whereHas('transaction', function($q) {
                                    $q->where('status', TransactionStatusEnum::SUCCESS);
                                })
                                ->whereJsonContains('meta->payment_type', 'initial_payment')
                                ->with('transaction')
                                ->first();

                            if ($prepaymentWalletTx && $prepaymentWalletTx->transaction) {
                                $prepaymentTransaction = $prepaymentWalletTx->transaction;
                            }
                        }

                        // Get or create transaction category for transfers (sender)
                        $senderCategory = TransactionCategory::firstOrCreate(
                            ['vendor_id' => $senderVendor->id, 'name' => 'Реализация'],
                            ['parent_id' => 0]
                        );

                        if ($transfer->is_installment && $prepaymentTransaction) {
                            // For installments: prepayment (1K) already exists, only create remaining balance (9K)
                            $prepaymentAmount = $prepaymentTransaction->amount;
                            $remainingBalance = $sale->total_price - $prepaymentAmount;

                            if ($remainingBalance > 0) {
                                $remainingTransaction = Transaction::create([
                                    'name' => 'Остаток по перемещению: ' . ($transfer->name ?? $transfer->transfer_number),
                                    'amount' => round($remainingBalance, 2),
                                    'transaction_category_id' => $senderCategory->id,
                                    'vendor_id' => $senderVendor->id,
                                    'description' => 'Остаточная сумма по рассрочке перемещения к ' . ($vendor->store->name ?? 'получателю'),
                                    'type' => TransactionTypeEnum::INCOME,
                                    'status' => TransactionStatusEnum::SUCCESS,
                                    'sale_id' => $sale->id,
                                ]);

                                // Update pending wallet transactions to success and link to remaining balance transaction
                                VendorWalletTransaction::where('receipt_id', $senderReceipt->id)
                                    ->where('vendor_id', $senderVendor->id)
                                    ->where('status', 'pending')
                                    ->update([
                                        'transaction_id' => $remainingTransaction->id,
                                        'status' => 'success',
                                        'paid_at' => now(),
                                    ]);

                                // Update the installment record to mark remaining balance as paid
                                $installment = OrderInstallment::where('external_transfer_id', $transfer->id)->first();
                                if ($installment) {
                                    $installment->remaining_balance = 0;
                                    $installment->is_paid = true;
                                    $installment->paid_at = now();
                                    $installment->save();
                                }
                            }
                        } else {
                            // For non-installment transfers or if no prepayment exists: create full amount transaction
                            $fullTransaction = Transaction::create([
                                'name' => 'Перемещение: ' . ($transfer->name ?? $transfer->transfer_number),
                                'amount' => $sale->total_price,
                                'transaction_category_id' => $senderCategory->id,
                                'vendor_id' => $senderVendor->id,
                                'description' => 'Внешнее перемещение товаров к ' . ($vendor->store->name ?? 'получателю'),
                                'type' => TransactionTypeEnum::INCOME,
                                'status' => TransactionStatusEnum::SUCCESS,
                                'sale_id' => $sale->id,
                            ]);

                            // Update pending wallet transactions to success and link to full transaction
                            VendorWalletTransaction::where('receipt_id', $senderReceipt->id)
                                ->where('vendor_id', $senderVendor->id)
                                ->where('status', 'pending')
                                ->update([
                                    'transaction_id' => $fullTransaction->id,
                                    'status' => 'success',
                                    'paid_at' => now(),
                                ]);

                            // If there's an installment (shouldn't happen for non-installment, but just in case)
                            $installment = OrderInstallment::where('external_transfer_id', $transfer->id)->first();
                            if ($installment) {
                                $installment->remaining_balance = 0;
                                $installment->is_paid = true;
                                $installment->paid_at = now();
                                $installment->save();
                            }
                        }
                    }
                }
            }

            // Generate receipt number for recipient
            $receiptNumber = 'RCP-TRF-' . date('YmdHis') . '-' . $vendor->id;

            // Ensure counterparty exists for recipient vendor (sender vendor as supplier)
            if ($senderVendor) {
                $this->ensureCounterpartyForTransfer($vendor->id, $senderVendor->id);
            }
            
            // Try to find the counterparty to set counterparty_id instead of recipient_vendor_id
            $counterparty = null;
            if ($senderVendor) {
                $counterparty = Counterparty::where('vendor_id', $vendor->id)
                    ->where('vendor_reference_id', $senderVendor->id)
                    ->where('type', 'supplier')
                    ->first();
            }
            
            $receipt = Receipt::create([
                'vendor_id' => $vendor->id, // Recipient vendor
                'warehouse_id' => $validated['to_warehouse_id'],
                'counterparty_id' => $counterparty?->id, // Set counterparty if found
                'recipient_vendor_id' => $senderVendor?->id, // Keep for backward compatibility
                'warehouse_transfer_id' => $transfer->id,
                'receipt_number' => $receiptNumber,
                'name' => 'Перемещение: ' . ($transfer->name ?? $transfer->transfer_number),
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'notes' => 'Получено от ' . ($senderVendor->name ?? $senderVendor->f_name ?? 'поставщика'),
            ]);

            // Create receipt items
            foreach ($receiptItems as $receiptItem) {
                ReceiptItem::create(array_merge($receiptItem, ['receipt_id' => $receipt->id]));
            }

            DB::commit();

            return response()->json([
                'message' => 'Перемещение успешно принято',
                'data' => new WarehouseTransferResource($transfer->fresh(['fromWarehouse', 'toWarehouse', 'toVendor', 'items.product']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при принятии перемещения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle external transfer rejection - return products to sender
     */
    public function rejectTransfer(Request $request, WarehouseTransfer $transfer)
    {
        $vendor = $this->getActingVendor();

        // Verify this vendor is the recipient
        if ($transfer->to_vendor_id !== $vendor->id) {
            return response()->json([
                'message' => 'Вы не являетесь получателем этого перемещения'
            ], 403);
        }

        // Verify transfer is in "sent" status
        if ($transfer->status !== 'sent') {
            return response()->json([
                'message' => 'Перемещение не может быть отклонено в текущем статусе'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get sender's store
            $senderVendor = Vendor::find($transfer->from_vendor_id);
            if (!$senderVendor || !$senderVendor->store) {
                throw new \Exception("Отправитель не найден");
            }

            $transfer->load('items.product');

            foreach ($transfer->items as $item) {
                $product = $item->product;
                // Return product to sender
                $product->store_id = $senderVendor->store->id;
                $product->warehouse_id = $transfer->from_warehouse_id;
                $product->save();
            }

            $transfer->update([
                'status' => 'cancelled',
                'notes' => ($transfer->notes ?? '') . ' [Отклонено получателем]'
            ]);

            // Delete the associated pending sale (since transfer was rejected)
            $sale = Sale::where('warehouse_transfer_id', $transfer->id)->first();
            if ($sale) {
                // Delete sale products first
                $sale->saleProducts()->delete();
                $sale->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Перемещение отклонено, товары возвращены отправителю',
                'data' => new WarehouseTransferResource($transfer->fresh(['fromWarehouse', 'toWarehouse', 'toVendor', 'items.product']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при отклонении перемещения: ' . $e->getMessage()
            ], 500);
        }
    }
}

