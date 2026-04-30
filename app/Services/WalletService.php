<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Get available wallets for a vendor with statistics
     */
    public function getAvailableWalletsWithStats($vendor, $includeStats = false)
    {
        // Ensure Личный wallet is always enabled for this vendor
        $personalWallet = Wallet::where('name', 'Личный')->where('type', 'personal')->first();
        if ($personalWallet) {
            VendorWallet::firstOrCreate(
                ['vendor_id' => $vendor->id, 'wallet_id' => $personalWallet->id],
                ['is_enabled' => true]
            );
        }

        // Ensure default cash wallet exists
        $cashName = 'Наличные';
        $wallet = Wallet::firstOrCreate(['name' => $cashName], ['logo' => null, 'is_available' => true]);
        VendorWallet::firstOrCreate(
            ['vendor_id' => $vendor->id, 'wallet_id' => $wallet->id],
            ['is_enabled' => true]
        );

        $vendorWallets = VendorWallet::with('wallet')
            ->where('vendor_id', $vendor->id)
            ->where('is_enabled', true)
            ->orderByRaw('1')
            ->get();

        $customWallets = Wallet::where('vendor_id', $vendor->id)
            ->where('is_available', true)
            ->whereNotIn('id', $vendorWallets->pluck('wallet_id'))
            ->get();

        $allWallets = collect();

        // Get transaction statistics if requested
        $txAgg = collect();
        $enabledTotalCount = 0;
        
        if ($includeStats) {
            $txAgg = VendorWalletTransaction::select('vendor_wallet_id',
                    DB::raw('COUNT(*) as tx_count'),
                    DB::raw('COALESCE(SUM(amount),0) as total_amount')
                )
                ->where('vendor_id', $vendor->id)
                ->where('status', 'success')
                ->groupBy('vendor_wallet_id')
                ->get()
                ->keyBy('vendor_wallet_id');

            foreach ($vendorWallets as $vw) {
                if ($vw->is_enabled) {
                    $stat = $txAgg->get($vw->id);
                    if ($stat) { $enabledTotalCount += (int) $stat->tx_count; }
                }
            }
        }

        foreach ($vendorWallets as $vw) {
            $effectiveLogo = $vw->logo ?: ($vw->wallet?->logo ?? null);

            // Only add storage/ prefix for uploaded files (wallet_logos/), not seeded assets
            if ($effectiveLogo && str_starts_with($effectiveLogo, 'wallet_logos/')) {
                $effectiveLogo = 'storage/' . $effectiveLogo;
            }

            $walletData = [
                'vendor_wallet_id' => $vw->id,
                'wallet_id' => $vw->wallet_id,
                'name' => $vw->wallet?->name,
                'logo' => $effectiveLogo,
                'is_enabled' => (bool) $vw->is_enabled,
                'type' => $vw->wallet?->type
            ];

            if ($includeStats) {
                $stat = $txAgg->get($vw->id);
                $count = $stat ? (int) $stat->tx_count : 0;
                $totalAmount = $stat ? (float) $stat->total_amount : 0.0;
                $percent = ($vw->is_enabled && $enabledTotalCount > 0)
                    ? round(($count / $enabledTotalCount) * 100, 2)
                    : 0.0;

                $walletData = array_merge($walletData, [
                    'transaction_count' => $count,
                    'transaction_share_percent' => $percent,
                    'total_amount' => $totalAmount,
                ]);
            }

            $allWallets->push($walletData);
        }

        foreach ($customWallets as $wallet) {
            // Only add storage/ prefix for uploaded files (wallet_logos/), not seeded assets
            $logo = null;
            if ($wallet->logo && str_starts_with($wallet->logo, 'wallet_logos/')) {
                $logo = 'storage/' . $wallet->logo;
            } else {
                $logo = $wallet->logo;
            }

            $walletData = [
                'vendor_wallet_id' => null,
                'wallet_id' => $wallet->id,
                'name' => $wallet->name,
                'logo' => $logo,
                'is_enabled' => true,
                'type' => $wallet->type
            ];

            if ($includeStats) {
                $walletData = array_merge($walletData, [
                    'transaction_count' => 0,
                    'transaction_share_percent' => 0.0,
                    'total_amount' => 0.0,
                ]);
            }

            $allWallets->push($walletData);
        }

        return $allWallets->values();
    }

    /**
     * Calculate financial fact (total balance) for vendor
     */
    public function calculateFinancialFact($vendor)
    {
        $today = Carbon::today()->toDateString();
        
        return (int) DB::table('transactions as t')
            ->leftJoin('sales as s', 's.id', '=', 't.sale_id')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->where('t.vendor_id', $vendor->id)
            ->where('t.status', 'success')
            ->whereDate('t.created_at', '<=', $today)
            ->selectRaw("COALESCE(SUM(CASE WHEN t.type='income' THEN (t.amount - COALESCE(o.points_used,0)) WHEN t.type='expense' THEN -t.amount WHEN t.type='dividends' THEN -t.amount ELSE 0 END),0) as fact")
            ->value('fact');
    }

    /**
     * Get wallets with statistics and financial fact
     */
    public function getWalletsWithFact($vendor)
    {
        $wallets = $this->getAvailableWalletsWithStats($vendor, true);
        $fact = $this->calculateFinancialFact($vendor);

        return [
            'wallets' => $wallets,
            'fact' => $fact
        ];
    }

    /**
     * Get simple available wallets (for transaction endpoints)
     */
    public function getAvailableWallets($vendor)
    {
        return $this->getAvailableWalletsWithStats($vendor, true);
    }

    /**
     * Get ALL wallets (enabled + disabled) for management purposes
     */
    public function getAllWalletsForManagement($vendor)
    {
        // Ensure Личный wallet is always enabled for this vendor
        $personalWallet = Wallet::where('name', 'Личный')->where('type', 'personal')->first();
        if ($personalWallet) {
            VendorWallet::firstOrCreate(
                ['vendor_id' => $vendor->id, 'wallet_id' => $personalWallet->id],
                ['is_enabled' => true]
            );
        }

        // Ensure default cash wallet exists
        $cashName = 'Наличные';
        $wallet = Wallet::firstOrCreate(['name' => $cashName], ['logo' => null, 'is_available' => true]);
        VendorWallet::firstOrCreate(
            ['vendor_id' => $vendor->id, 'wallet_id' => $wallet->id],
            ['is_enabled' => true]
        );

        // Get ALL vendor wallets (enabled + disabled)
        $vendorWallets = VendorWallet::with('wallet')
            ->where('vendor_id', $vendor->id)
            ->orderByRaw('1')
            ->get();

        // Get available global wallets that vendor hasn't activated yet
        $availableGlobalWallets = Wallet::where('is_available', true)
            ->whereNull('vendor_id') // Global wallets only
            ->whereNotIn('id', $vendorWallets->pluck('wallet_id'))
            ->get();

        $allWallets = collect();

        // Add existing vendor wallets (enabled + disabled)
        foreach ($vendorWallets as $vw) {
            $effectiveLogo = $vw->logo ?: ($vw->wallet?->logo ?? null);

            // Only add storage/ prefix for uploaded files (wallet_logos/), not seeded assets
            if ($effectiveLogo && str_starts_with($effectiveLogo, 'wallet_logos/')) {
                $effectiveLogo = 'storage/' . $effectiveLogo;
            }

            $allWallets->push([
                'vendor_wallet_id' => $vw->id,
                'wallet_id' => $vw->wallet_id,
                'name' => $vw->wallet?->name ?? 'Unknown',
                'logo' => $effectiveLogo,
                'balance' => $vw->balance ?? 0,
                'is_enabled' => $vw->is_enabled,
                'status' => $vw->is_enabled ? 'active' : 'inactive'
            ]);
        }

        // Add available global wallets (not yet activated)
        foreach ($availableGlobalWallets as $wallet) {
            $effectiveLogo = $wallet->logo;
            if ($effectiveLogo && str_starts_with($effectiveLogo, 'wallet_logos/')) {
                $effectiveLogo = 'storage/' . $effectiveLogo;
            }

            $allWallets->push([
                'vendor_wallet_id' => null,
                'wallet_id' => $wallet->id,
                'name' => $wallet->name,
                'logo' => $effectiveLogo,
                'balance' => 0,
                'is_enabled' => false,
                'status' => 'available_to_activate'
            ]);
        }

        return $allWallets->toArray();
    }
}
