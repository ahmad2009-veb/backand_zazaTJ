<?php

namespace App\Http\Controllers\Api\V3\vendor;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\VendorWallet;
use App\Models\TransactionCategory;
use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use Illuminate\Http\Request;
use App\Models\OrderInstallment;
use App\Models\TransferInstallment;
use Illuminate\Support\Facades\DB;
use App\Models\VendorWalletTransaction;
use App\Http\Controllers\Controller;
use App\Http\Traits\VendorEmployeeAccess;
use App\Http\Resources\Vendor\OrderInstallmentResource;
use App\Http\Resources\Vendor\TransferInstallmentResource;

class OrderInstallmentsController extends Controller
{
    use VendorEmployeeAccess;

    /**
     * Get installments list with filtering options
     */
    public function index(Request $request)
    {
        $vendorId = $this->getActingVendor()->id;
        $installmentType = $request->input('installment_type', 'orders'); // Default to 'orders' if not specified

        // Filter by installment type: 'orders' or 'transfer'
        if ($installmentType === 'transfer') {
            return $this->getTransferInstallments($request, $vendorId);
        } elseif ($installmentType === 'orders') {
            return $this->getOrderInstallments($request, $vendorId);
        } else {
            // If invalid type, return both (or default to orders)
            return $this->getOrderInstallments($request, $vendorId);
        }
    }

    /**
     * Get order installments
     */
    private function getOrderInstallments(Request $request, int $vendorId)
    {
        $query = OrderInstallment::with(['order.customer', 'order.store', 'order.details'])
            ->where('created_by', $vendorId)
            ->whereNull('external_transfer_id') // Only order installments
            ->whereNull('status'); // Only installments without status (for orders)

        if ($request->has('is_paid')) {
            $query->where('is_paid', (bool)$request->is_paid);
        }

        if ($request->has('is_expired')) {
            $query->where('due_date', '<', Carbon::today())->where('is_paid', false);
        }

        if ($request->has('due_date')) {
            $query->whereDate('due_date', Carbon::parse($request->due_date)->format('Y-m-d'));
            if ($request->has('is_paid')) {
                $query->where('is_paid', (bool)$request->is_paid);
            }
        }

        $installments = $query->orderBy('due_date')
            ->paginate($request->per_page ?? 12);

        return OrderInstallmentResource::collection($installments);
    }

    /**
     * Get transfer installments
     */
    private function getTransferInstallments(Request $request, int $vendorId)
    {
        $query = OrderInstallment::with(['externalTransfer.toVendor', 'externalTransfer.fromWarehouse', 'externalTransfer.items.product'])
            ->where('created_by', $vendorId)
            ->whereNotNull('external_transfer_id') // Only transfer installments
            ->whereNotNull('status'); // Only installments with status (pending/completed)

        if ($request->has('is_paid')) {
            $query->where('is_paid', (bool)$request->is_paid);
        }

        if ($request->has('is_expired')) {
            $query->where('due_date', '<', Carbon::today())->where('is_paid', false);
        }

        if ($request->has('due_date')) {
            $query->whereDate('due_date', Carbon::parse($request->due_date)->format('Y-m-d'));
            if ($request->has('is_paid')) {
                $query->where('is_paid', (bool)$request->is_paid);
            }
        }

        $installments = $query->orderBy('due_date')
            ->paginate($request->per_page ?? 12);

        return TransferInstallmentResource::collection($installments);
    }

    /**
     * Get detailed information about a specific installment
     */
    public function show($id)
    {
        $vendorId = $this->getActingVendor()->id;

        $installment = OrderInstallment::with([
            'order.customer',
            'order.store',
            'order.details.product'
        ])
        ->where('id', $id)
        ->where('created_by', $vendorId)
        ->firstOrFail();

        $order = $installment->order;

        return response()->json([
            'id' => $installment->id,
            'order_id' => $installment->order_id,
            'initial_payment' => $installment->initial_payment,
            'total_due' => $installment->total_due,
            'remaining_balance' => $installment->remaining_balance,
            'due_date' => optional($installment->due_date)?->toDateString(),
            'is_paid' => $installment->is_paid,
            'created_at' => $installment->created_at->toDateTimeString(),

            'customer' => trim(($order->customer?->f_name ?? '') . ' ' . ($order->customer?->l_name ?? '')) ?: '—',
            'customer_phone' => $order->customer?->phone ?? '—',
            'store' => $order->store->name ?? '—',

            'product' => $order->details->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'quantity' => $detail->quantity,
                    'price' => $detail->quantity * $detail->price,
                    'details' => [
                        'name' => $detail->product->name ?? '—',
                        // 'quantity' => $detail->product->quantity ?? '—',
                        'image' => $detail->product?->image !== null ? url('storage/product/' . $detail->product->image) : null,
                    ],
                ];
            }),

            'history' => $installment->payments->map(function ($payment) {
                return [
                    'date' => $payment->paid_at->toDateString(),
                    'amount' => $payment->amount,
                ];
            }),
            'total_paid' => $installment->total_due - $installment->remaining_balance,
        ]);
    }

    /**
     * Process installment payment
     */
    public function pay(Request $request)
    {
        $validated = $request->validate([
            'installment_id' => 'required|exists:order_installments,id',
            'amount' => 'required|numeric|min:0.01',
            'wallet_id' => 'required|integer|exists:wallets,id',
        ]);

        $installment = OrderInstallment::with('order')
            ->where('id', $validated['installment_id'])
            ->where('created_by', $this->getActingVendor()->id)
            ->firstOrFail();

        $installment->payments()->create([
            'amount' => $validated['amount'],
            'paid_at' => now(),
        ]);

        $installment->remaining_balance -= $validated['amount'];
        if ($installment->remaining_balance <= 0) {
            $installment->remaining_balance = 0;
            $installment->is_paid = true;
            $installment->paid_at = now();

            if ($installment->order) {
                $installment->order->order_status = 'successful';
                $installment->order->payment_status = 'paid';
                $installment->order->save();
            }
        }

        $installment->save();

        // Record transaction and wallet transaction for installment payment
        try {
            $vendorId = $this->getActingVendor()->id;

            // Find or create the "Реализация" category for this vendor
            $category = TransactionCategory::firstOrCreate(
                ['vendor_id' => $vendorId, 'name' => 'Реализация'],
                ['parent_id' => 0]
            );

            // Create Transaction record for installment payment
            $installmentTransaction = Transaction::create([
                'name' => 'Оплата рассрочки по заказу #' . $installment->order_id,
                'amount' => round($validated['amount'], 2),
                'transaction_category_id' => $category->id,
                'description' => 'Частичная оплата рассрочки по заказу #' . $installment->order_id,
                'type' => TransactionTypeEnum::INCOME,
                'vendor_id' => $vendorId,
                'status' => TransactionStatusEnum::SUCCESS
            ]);

            // Find the vendor_wallet record that matches the global wallet_id
            $vendorWallet = VendorWallet::where('vendor_id', $vendorId)
                ->where('wallet_id', $validated['wallet_id'])
                ->first();

            if ($vendorWallet) {
                VendorWalletTransaction::create([
                    'vendor_id' => $vendorId,
                    'vendor_wallet_id' => $vendorWallet->id,
                    'order_id' => $installment->order_id,
                    'transaction_id' => $installmentTransaction->id,
                    'amount' => round($validated['amount'], 2),
                    'status' => 'success',
                    'paid_at' => now(),
                    'meta' => [
                        'source' => 'installment_payment',
                        'installment_id' => $installment->id,
                        'payment_amount' => $validated['amount']
                    ]
                ]);
            } else {
                \Log::warning('Wallet not available for vendor during installment payment', [
                    'vendor_id' => $vendorId,
                    'wallet_id' => $validated['wallet_id'],
                    'installment_id' => $installment->id
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Transaction and wallet capture for installment payment failed: ' . $e->getMessage(), ['installment_id' => $installment->id ?? null]);
        }

        return response()->json([
            'message' => 'Оплата успешно добавлена.',
            'remaining_balance' => $installment->remaining_balance,
            'is_paid' => $installment->is_paid,
        ]);
    }
}
