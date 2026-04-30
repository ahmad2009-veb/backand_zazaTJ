<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Models\Wallet;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Traits\VendorEmployeeAccess;
use App\Http\Resources\Vendor\VendorWalletTransactionResource;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


class WalletController extends Controller
{
    use VendorEmployeeAccess;

    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function availableWallets()
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $this->walletService->getWalletsWithFact($vendor);

        return response()->json($data);
    }

    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $vendor = $this->getActingVendor();

        // Check if vendor already has a wallet with this name
        $existingWallet = Wallet::where(function ($query) use ($vendor, $request) {
            $query->where('vendor_id', $vendor->id)
                  ->where('name', $request->name);
        })->orWhere(function ($query) use ($vendor, $request) {
            $query->whereNull('vendor_id')
                  ->where('name', $request->name)
                  ->whereHas('vendorWallets', function ($q) use ($vendor) {
                      $q->where('vendor_id', $vendor->id);
                  });
        })->exists();

        if ($existingWallet) {
            return response()->json([
                'message' => 'You already have a wallet with this name',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('wallet_logos', 'public');
            }

            $wallet = Wallet::create([
                'name' => $request->name,
                'logo' => $logoPath,
                'is_available' => true,
                'vendor_id' => $vendor->id,
            ]);

            $vendorWallet = VendorWallet::create([
                'vendor_id' => $vendor->id,
                'wallet_id' => $wallet->id,
                'is_enabled' => true,
            ]);

            DB::commit();

            // Format logo path for response
            $responseLogo = null;
            if ($wallet->logo && str_starts_with($wallet->logo, 'wallet_logos/')) {
                $responseLogo = 'storage/' . $wallet->logo;
            } else {
                $responseLogo = $wallet->logo;
            }

            return response()->json([
                'message' => 'Custom wallet created successfully',
                'wallet' => [
                    'vendor_wallet_id' => $vendorWallet->id,
                    'wallet_id' => $wallet->id,
                    'name' => $wallet->name,
                    'logo' => $responseLogo,
                    'is_enabled' => true,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if wallet creation failed
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }

            return response()->json([
                'message' => 'Failed to create custom wallet',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function transactions(Request $request)
    {
        $request->validate([
            'wallet_id' => 'nullable|integer|exists:wallets,id',
            'status' => 'nullable|string|in:success,pending,failed',
            'source' => 'nullable|string',
            'date_from' => 'nullable|date|before_or_equal:date_to',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = VendorWalletTransaction::with([
            'vendorWallet.wallet',
            'order',
            'transaction'
        ])->where('vendor_id', $vendor->id);

        if ($request->wallet_id) {
            $query->whereHas('vendorWallet', function ($q) use ($request) {
                $q->where('wallet_id', $request->wallet_id);
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->source) {
            $query->whereJsonContains('meta->source', $request->source);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->format('Y-m-d'));
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->format('Y-m-d'));
        }

        $transactions = $query->latest('created_at')
            ->paginate($request->per_page ?? 15);

        return VendorWalletTransactionResource::collection($transactions);
    }

    public function transactionStats(Request $request)
    {
        $request->validate([
            'wallet_id' => 'nullable|integer|exists:wallets,id',
            'date_from' => 'nullable|date|before_or_equal:date_to',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = VendorWalletTransaction::where('vendor_id', $vendor->id)
            ->where('status', 'success');

        if ($request->wallet_id) {
            $query->whereHas('vendorWallet', function ($q) use ($request) {
                $q->where('wallet_id', $request->wallet_id);
            });
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->format('Y-m-d'));
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->format('Y-m-d'));
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_transactions,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(AVG(amount), 0) as average_amount,
            COALESCE(MAX(amount), 0) as max_amount,
            COALESCE(MIN(amount), 0) as min_amount
        ')->first();

        $sourceStats = VendorWalletTransaction::where('vendor_id', $vendor->id)
            ->where('status', 'success')
            ->when($request->wallet_id, function ($q) use ($request) {
                $q->whereHas('vendorWallet', function ($subQ) use ($request) {
                    $subQ->where('wallet_id', $request->wallet_id);
                });
            })
            ->when($request->date_from, function ($q) use ($request) {
                $q->whereDate('created_at', '>=', Carbon::parse($request->date_from)->format('Y-m-d'));
            })
            ->when($request->date_to, function ($q) use ($request) {
                $q->whereDate('created_at', '<=', Carbon::parse($request->date_to)->format('Y-m-d'));
            })
            ->selectRaw("
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.source')) as source,
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total_amount
            ")
            ->groupBy('source')
            ->get();

        return response()->json([
            'total_transactions' => (int) $stats->total_transactions,
            'total_amount' => (float) $stats->total_amount,
            'average_amount' => (float) $stats->average_amount,
            'max_amount' => (float) $stats->max_amount,
            'min_amount' => (float) $stats->min_amount,
            'by_source' => $sourceStats->map(function ($item) {
                return [
                    'source' => $item->source ?: 'unknown',
                    'count' => (int) $item->count,
                    'total_amount' => (float) $item->total_amount,
                ];
            }),
        ]);
    }

    public function makeTransaction(Request $request)
    {
        $request->validate([
            'from_wallet_id' => 'required|integer|exists:wallets,id',
            'to_wallet_id' => 'required|integer|exists:wallets,id|different:from_wallet_id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        DB::beginTransaction();
        try {
            $fromVendorWallet = VendorWallet::with('wallet')->where('vendor_id', $vendor->id)
                ->where('wallet_id', $request->from_wallet_id)
                ->first();

            $toVendorWallet = VendorWallet::with('wallet')->where('vendor_id', $vendor->id)
                ->where('wallet_id', $request->to_wallet_id)
                ->first();

            if (!$fromVendorWallet) {
                throw new \Exception('Source wallet not available for this vendor');
            }

            if (!$toVendorWallet) {
                throw new \Exception('Destination wallet not available for this vendor');
            }

            // Check if trying to transfer FROM Личный wallet (not allowed)
            if ($fromVendorWallet->wallet && $fromVendorWallet->wallet->name === 'Личный') {
                throw new \Exception('Transfers from Личный wallet are not allowed');
            }

            $amount = (float) $request->amount;
            $description = $request->description ?: 'Wallet transfer';
            $reference = 'WT-' . time() . '-' . $vendor->id;

            $outgoingTransaction = VendorWalletTransaction::create([
                'vendor_id' => $vendor->id,
                'vendor_wallet_id' => $fromVendorWallet->id,
                'order_id' => null,
                'transaction_id' => null,
                'amount' => -$amount, // shoti minusa baroi perevod
                'status' => 'success',
                'reference' => $reference,
                'paid_at' => now(),
                'meta' => [
                    'source' => 'wallet_transfer',
                    'transfer_type' => 'outgoing',
                    'description' => $description,
                    'from_wallet_id' => $request->from_wallet_id,
                    'to_wallet_id' => $request->to_wallet_id,
                    'related_transaction_id' => null
                ]
            ]);

            $incomingTransaction = VendorWalletTransaction::create([
                'vendor_id' => $vendor->id,
                'vendor_wallet_id' => $toVendorWallet->id,
                'order_id' => null,
                'transaction_id' => null,
                'amount' => $amount, // shoti pilusa baroi perevod
                'status' => 'success',
                'reference' => $reference,
                'paid_at' => now(),
                'meta' => [
                    'source' => 'wallet_transfer',
                    'transfer_type' => 'incoming',
                    'description' => $description,
                    'from_wallet_id' => $request->from_wallet_id,
                    'to_wallet_id' => $request->to_wallet_id,
                    'related_transaction_id' => $outgoingTransaction->id
                ]
            ]);

            $outgoingTransaction->update([
                'meta' => array_merge($outgoingTransaction->meta, [
                    'related_transaction_id' => $incomingTransaction->id
                ])
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Wallet transfer completed successfully',
                'transfer' => [
                    'reference' => $reference,
                    'amount' => $amount,
                    'description' => $description,
                    'from_wallet' => [
                        'id' => $request->from_wallet_id,
                        'name' => $fromVendorWallet->wallet->name
                    ],
                    'to_wallet' => [
                        'id' => $request->to_wallet_id,
                        'name' => $toVendorWallet->wallet->name
                    ],
                    'outgoing_transaction_id' => $outgoingTransaction->id,
                    'incoming_transaction_id' => $incomingTransaction->id,
                    'created_at' => now()->format('Y-m-d H:i:s')
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to complete wallet transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
