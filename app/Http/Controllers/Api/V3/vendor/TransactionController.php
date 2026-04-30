<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\Vendor\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Traits\VendorEmployeeAccess;
use App\Models\Transaction;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;


use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    use VendorEmployeeAccess;
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string'],
            'type' => ['nullable', 'string', Rule::enum(TransactionTypeEnum::class)],
            'transaction_category_id' => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'date_from'  => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'requestedDate' => ['nullable', 'date'],
        ]);

        $transactions = auth()->user()->transactions()->latest()
            ->with(['sale.order'])
            ->filter($request)
            ->paginate($request->input('per_page', 10));
        return TransactionResource::collection($transactions);
    }

    public function countTotals(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string'],
            'type' => ['nullable', 'string', Rule::enum(TransactionTypeEnum::class)],
            'transaction_category_id' => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'date_from'  => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'requestedDate' => ['nullable', 'date'],
        ]);

        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $vendorId = $vendor->id;

        // Build base query for filtering (same as calendar method)
        $baseQuery = DB::table('transactions as t')
            ->where('t.vendor_id', $vendorId)
            ->where('t.status', TransactionStatusEnum::SUCCESS->value);

        // Apply filters from request
        if ($request->has('date_from')) {
            $date_from = Carbon::parse($request->date_from)->format('Y-m-d');
            $baseQuery->whereDate('t.created_at', '>=', $date_from);
        }
        if ($request->has('date_to')) {
            $date_to = Carbon::parse($request->date_to)->format('Y-m-d');
            $baseQuery->whereDate('t.created_at', '<=', $date_to);
        }
        if ($request->has('transaction_category_id')) {
            $baseQuery->where('t.transaction_category_id', $request->transaction_category_id);
        }
        if ($request->has('type')) {
            $baseQuery->where('t.type', $request->type);
        }

        // Calculate total income (with points_used adjustment, similar to calendar method)
        $totalIncome = (float) (clone $baseQuery)
            ->leftJoin('sales as s', 's.id', '=', 't.sale_id')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->where('t.type', TransactionTypeEnum::INCOME->value)
            ->selectRaw("COALESCE(SUM(t.amount - COALESCE(o.points_used, 0)), 0) as total")
            ->value('total');

        // Calculate total expense
        $totalExpense = (float) (clone $baseQuery)
            ->where('t.type', TransactionTypeEnum::EXPENSE->value)
            ->sum('t.amount');

        return response()->json([
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
        ]);
    }

    public function store(TransactionRequest $request)
    {
        DB::beginTransaction();
        try {
            $transaction = Transaction::query()->create([
                'name' => $request->name,
                'amount' => $request->amount,
                'transaction_category_id' => $request->transaction_category_id,
                'description' => $request->description,
                'type' => $request->type,
                'vendor_id' => auth()->user()->id,
                'status' => TransactionStatusEnum::SUCCESS
            ]);

            if ($request->wallet_id) {
                $vendorWallet = VendorWallet::where('vendor_id', auth()->user()->id)
                    ->where('wallet_id', $request->wallet_id)
                    ->first();

                if ($vendorWallet) {
                    // Determine wallet amount based on transaction type
                    $walletAmount = match($request->type) {
                        'income' => $request->amount,           // Positive for income
                        'expense' => -$request->amount,         // Negative for expense
                        'dividends' => -$request->amount,       // Negative for dividends
                        default => $request->amount
                    };

                    VendorWalletTransaction::create([
                        'vendor_id' => auth()->user()->id,
                        'vendor_wallet_id' => $vendorWallet->id,
                        'order_id' => null,
                        'transaction_id' => $transaction->id,
                        'amount' => $walletAmount,
                        'status' => 'success',
                        'paid_at' => now(),
                        'meta' => [
                            'source' => 'direct_transaction',
                            'transaction_type' => $request->type,
                            'transaction_name' => $request->name,
                            'wallet_type' => $vendorWallet->wallet?->type ?? 'regular'
                        ]
                    ]);
                } else {
                    throw new \Exception('Wallet not available for this vendor');
                }
            }

            DB::commit();
            return response()->json(['data' => 'Успешно сохранено']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Ошибка при создании транзакции: ' . $e->getMessage()], 500);
        }
    }

    public function typesOptions()
    {
        return response()->json(['data' => TransactionTypeEnum::options()]);
    }

    public function show(Transaction $transaction)
    {
        // Load necessary relationships for the response
        $transaction->load([
            'categoryTransaction.parent',
            'vendorWalletTransactions' => function ($query) {
                $query->with(['vendorWallet.wallet']);
            }
        ]);
        
        return TransactionResource::make($transaction);
    }

    public function update(Request $request, Transaction $transaction)
    {
        // For PUT requests with form-data, PHP doesn't populate $_POST automatically
        // We need to manually parse the input stream
        if ($request->method() === 'PUT' && empty($request->all())) {
            $contentType = $request->header('Content-Type', '');
            
            // Handle multipart/form-data
            if (strpos($contentType, 'multipart/form-data') !== false) {
                // For multipart, we need to parse it differently
                // Try to get from php://input
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    // Parse multipart form data
                    $boundary = substr($contentType, strpos($contentType, 'boundary=') + 9);
                    $parts = explode('--' . $boundary, $input);
                    $data = [];
                    
                    foreach ($parts as $part) {
                        if (empty(trim($part)) || $part === '--') continue;
                        
                        if (preg_match('/name="([^"]+)"/', $part, $matches)) {
                            $name = $matches[1];
                            // Get the value (after the headers and empty line)
                            $value = substr($part, strpos($part, "\r\n\r\n") + 4);
                            $value = trim($value, "\r\n");
                            
                            // Handle array notation (wallet_ids[])
                            if (preg_match('/^(.+)\[\]$/', $name, $arrayMatches)) {
                                $arrayName = $arrayMatches[1];
                                if (!isset($data[$arrayName])) {
                                    $data[$arrayName] = [];
                                }
                                $data[$arrayName][] = $value;
                            } else {
                                $data[$name] = $value;
                            }
                        }
                    }
                    
                    if (!empty($data)) {
                        $request->merge($data);
                    }
                }
            } else {
                // Handle application/x-www-form-urlencoded
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    parse_str($input, $parsed);
                    if (!empty($parsed)) {
                        $request->merge($parsed);
                    }
                }
            }
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'amount' => 'required|numeric',
            'transaction_category_id' => 'required|integer|exists:transaction_categories,id',
            'description' => 'nullable|string',
            'type' => ['required', 'string', Rule::enum(TransactionTypeEnum::class)],
            'wallet_ids' => 'nullable|array',
            'wallet_ids.*' => 'integer|exists:wallets,id'
        ]);

        DB::beginTransaction();
        try {
            // Update transaction using validated data
            $transaction->update([
                'name' => $validated['name'],
                'amount' => $validated['amount'],
                'transaction_category_id' => $validated['transaction_category_id'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type']
            ]);

            // Delete existing vendor wallet transactions for this transaction
            VendorWalletTransaction::where('transaction_id', $transaction->id)->delete();

            // Handle wallet_ids from form-data (can be array or single value)
            $walletIds = [];
            if (isset($validated['wallet_ids']) && !empty($validated['wallet_ids'])) {
                $walletIdsInput = $validated['wallet_ids'];
                // Handle both array and single value from form-data
                if (is_array($walletIdsInput)) {
                    $walletIds = $walletIdsInput;
                } elseif (!empty($walletIdsInput)) {
                    $walletIds = [$walletIdsInput];
                }
            }

            // Create new vendor wallet transactions if wallet_ids provided
            if (!empty($walletIds)) {
                $vendorId = auth()->user()->id;
                $totalAmount = (float) $validated['amount'];
                $walletCount = count($walletIds);
                
                // Split amount equally across wallets
                $amountPerWallet = $totalAmount / $walletCount;
                $transactionType = $validated['type'];

                foreach ($walletIds as $walletId) {
                    $vendorWallet = VendorWallet::where('vendor_id', $vendorId)
                        ->where('wallet_id', $walletId)
                        ->first();

                    if ($vendorWallet) {
                        // Determine wallet amount based on transaction type
                        $walletAmount = match($transactionType) {
                            'income' => $amountPerWallet,           // Positive for income
                            'expense' => -$amountPerWallet,         // Negative for expense
                            'dividends' => -$amountPerWallet,       // Negative for dividends
                            default => $amountPerWallet
                        };

                        VendorWalletTransaction::create([
                            'vendor_id' => $vendorId,
                            'vendor_wallet_id' => $vendorWallet->id,
                            'order_id' => null,
                            'transaction_id' => $transaction->id,
                            'amount' => round($walletAmount, 2),
                            'status' => 'success',
                            'paid_at' => now(),
                            'meta' => [
                                'source' => 'direct_transaction',
                                'transaction_type' => $transactionType,
                                'transaction_name' => $validated['name'],
                                'wallet_type' => $vendorWallet->wallet?->type ?? 'regular'
                            ]
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'ok']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Ошибка при обновлении транзакции: ' . $e->getMessage()], 500);
        }
    }

    public function calendar(Request $request)
    {
        $request->validate([
            'date_from'  => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:date_to'],
            'date_to'    => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $from = $request->input('date_from');
        $to = $request->input('date_to');
        $vendorId = $vendor->id;


        $opening = (int) DB::table('transactions as t')
            ->leftJoin('sales as s', 's.id', '=', 't.sale_id')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->where('t.vendor_id', $vendorId)
            ->where('t.status', 'success')
            ->whereDate('t.created_at', '<', $from)
            ->selectRaw("COALESCE(SUM(CASE WHEN t.type='income' THEN (t.amount - COALESCE(o.points_used,0)) WHEN t.type='expense' THEN -t.amount WHEN t.type='dividends' THEN -t.amount ELSE 0 END),0) as bal")
            ->value('bal');


        $txRows = DB::table('transactions as t')
            ->leftJoin('sales as s', 's.id', '=', 't.sale_id')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->where('t.vendor_id', $vendorId)
            ->where('t.status', 'success')
            ->whereBetween(DB::raw('DATE(t.created_at)'), [$from, $to])
            ->selectRaw("DATE(t.created_at) as d,
                         SUM(CASE WHEN t.type='income' THEN (t.amount - COALESCE(o.points_used,0)) ELSE 0 END) as income,
                         SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END) as expense,
                         SUM(CASE WHEN t.type='dividends' THEN t.amount ELSE 0 END) as dividends")
            ->groupBy('d')
            ->get();

        $incomeByDate = [];
        $expenseByDate = [];
        $dividendsByDate = [];
        foreach ($txRows as $row) {
            $incomeByDate[$row->d] = (int) $row->income;
            $expenseByDate[$row->d] = (int) $row->expense;
            $dividendsByDate[$row->d] = (int) ($row->dividends ?? 0);
        }


        $insRows = DB::table('order_installments as i')
            ->where('i.created_by', $vendorId)
            ->where('i.remaining_balance', '>', 0)
            ->whereBetween(DB::raw('DATE(i.created_at)'), [$from, $to])
            ->selectRaw('DATE(i.created_at) as d, SUM(i.remaining_balance) as installment')
            ->groupBy('d')
            ->get();

        $installmentByDate = [];
        foreach ($insRows as $row) {
            $installmentByDate[$row->d] = (int) $row->installment;
        }


        $days = [];
        $running = (int) $opening;
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $income = (int) ($incomeByDate[$key] ?? 0);
            $expense = (int) ($expenseByDate[$key] ?? 0);
            $installment = (int) ($installmentByDate[$key] ?? 0);


            $dividends = (int) ($dividendsByDate[$key] ?? 0);
            $running += ($income - $expense - $dividends);

            $days[] = [
                'date' => $key,
                'income' => $income,
                'expense' => $expense,
                'installment' => $installment,
                'fact' => $running,
            ];
        }

        return response()->json([
            'opening_balance' => (int) $opening,
            'date_from' => $from,
            'date_to' => $to,
            'days' => $days,
        ]);

    }


}
