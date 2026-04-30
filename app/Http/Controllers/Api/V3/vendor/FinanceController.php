<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\ProductProfitabilityResource;
use App\Services\FinanceService;
use App\Services\ProductService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Http\Traits\VendorEmployeeAccess;


class FinanceController extends Controller
{
    use VendorEmployeeAccess;

    public function __construct(
        public FinanceService $financeService,
        public ProductService $productService,
        public WalletService $walletService
    ) {}
    public function productProfitability(Request $request)
    {
        $search = $request->input('search');

        $vendor = $this->getActingVendor();
        $products = $vendor->products()
            ->when($search, function ($query, $search) {
                return   $query->where('products.name', 'LIKE', '%' . $search . '%');
            })->paginate($request->input('per_page', 10));


        return ProductProfitabilityResource::collection($products);
    }

    public function mainIncomeStatistics()
    {
        $vendor = $this->getActingVendor();
        $data =  $this->financeService->mainIncomeStatistics('vendor', $vendor);
        return response()->json($data);
    }


    public function monthlyFinanceStatistics()
    {
        $vendor = $this->getActingVendor();
        $data =   $this->financeService->monthlyFinanceStatistics('vendor', $vendor);
        return response($data);
    }


    public function productProfitabilityExport(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:all,id,date',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'id_from' => 'nullable|integer',
            'id_to' => 'nullable|integer'
        ]);
        $vendor = $this->getActingVendor();
        $data =   $this->productService->exportProductProfitability($request, $vendor);
        return (new FastExcel($data))->download('product_profitability' . now()->format('Y-m-d') . '.xlsx');
    }

    public function marginStatistics()
    {
        $vendor = $this->getActingVendor();
        $topSalesCount = $vendor->products()->orderBy('sales_count', 'desc')->first();
        $topRevenue = $vendor->products()->orderBy('total_revenue', 'desc')->first();
        $topNotSale = $vendor->products()->orderBy('sales_count', 'asc')->first();
        return [
            [
                'title' => 'Топ по количеству продаж',
                'product_name ' => $topSalesCount->name,
                'total_revenue' => $topSalesCount->total_revenue,
                'quantity' => $topSalesCount->sales_count
            ],
            [
                'title' => 'Топ по прибыльности',
                'product_name' => $topRevenue->name,
                'total_profit' => $topRevenue->total_profit,
                'quantity' => $topRevenue->sales_count,
            ],
            [
                'title' => 'Топ по не рентабельности',
                'product_name' => $topNotSale->name,
                'quantity' => $topNotSale->sales_count,
                'days' => Carbon::parse($topNotSale->created_at)->diffInDays(now())
            ]
        ];
    }

    public function calendar(string $yearMonth, Request $request)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return response()->json(['message' => 'Invalid yearMonth format. Use YYYY-MM'], 422);
        }

        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid date'], 422);
        }
        $end = $start->copy()->endOfMonth();

        $from = $start->toDateString();
        $to = $end->toDateString();
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
            ->whereBetween(DB::raw('DATE(i.due_date)'), [$from, $to])
            ->selectRaw('DATE(i.due_date) as d, SUM(i.remaining_balance) as installment')
            ->groupBy('d')
            ->get();

        $installmentByDate = [];
        foreach ($insRows as $row) {
            $installmentByDate[$row->d] = (int) $row->installment;
        }

        // OPTIMIZED: Pre-calculate scheduled transaction dates for the month
        $scheduledDates = $this->getScheduledTransactionDates($vendorId, $from, $to);

        $days = [];
        $running = (int) $opening;
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
                'has_transaction' => isset($scheduledDates[$key]),
            ];
        }

        return response()->json([
            'opening_balance' => (int) $opening,
            'date_from' => $from,
            'date_to' => $to,
            'days' => $days,
        ]);
    }

    /**
     * OPTIMIZED: Get scheduled transaction dates for a date range using efficient SQL
     * Returns array with date as key for O(1) lookup
     */
    private function getScheduledTransactionDates($vendorId, $from, $to)
    {
        // Strategy 1: Direct SQL for one-time schedules
        $oneTimeSchedules = DB::table('transaction_schedules')
            ->where('vendor_id', $vendorId)
            ->where('status', 'active')
            ->where('cycle_type', 'one_time')
            ->whereBetween(DB::raw('COALESCE(scheduled_date, DATE(created_at))'), [$from, $to])
            ->where(function($query) {
                $query->where('requires_approval', false)
                      ->orWhereRaw('JSON_CONTAINS(COALESCE(approved_dates, "[]"), JSON_QUOTE(CAST(COALESCE(scheduled_date, DATE(created_at)) AS CHAR)))');
            })
            ->selectRaw('COALESCE(scheduled_date, DATE(created_at)) as schedule_date')
            ->pluck('schedule_date')
            ->toArray();

        // Strategy 2: Calculate recurring schedules efficiently
        $recurringSchedules = DB::table('transaction_schedules')
            ->where('vendor_id', $vendorId)
            ->where('status', 'active')
            ->whereIn('cycle_type', ['weekly', 'monthly'])
            ->select('id', 'cycle_type', 'scheduled_date', 'created_at', 'requires_approval', 'approved_dates')
            ->get();

        $scheduledDates = [];

        // Add one-time schedules
        foreach ($oneTimeSchedules as $date) {
            $scheduledDates[$date] = true;
        }

        // Add recurring schedules (optimized calculation)
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

        foreach ($recurringSchedules as $schedule) {
            $scheduledDate = Carbon::parse($schedule->scheduled_date ?? $schedule->created_at);
            $approvedDates = json_decode($schedule->approved_dates ?? '[]', true);

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateString = $date->toDateString();

                $shouldAppear = match($schedule->cycle_type) {
                    'weekly' => $date->dayOfWeek === $scheduledDate->dayOfWeek && $dateString >= $scheduledDate->toDateString(),
                    'monthly' => $date->day === $scheduledDate->day && $dateString >= $scheduledDate->toDateString(),
                    default => false
                };

                if ($shouldAppear) {
                    $isApproved = !$schedule->requires_approval || in_array($dateString, $approvedDates);
                    if ($isApproved) {
                        $scheduledDates[$dateString] = true;
                    }
                }
            }
        }

        return $scheduledDates;
    }

    public function wallets(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) return response()->json(['message' => 'Unauthenticated'], 401);

        // For finance management - return ALL wallets (enabled + disabled + available to activate)
        $allWallets = $this->walletService->getAvailableWallets($vendor);
        $fact = $this->walletService->calculateFinancialFact($vendor);

        return response()->json([
            'wallets' => $allWallets,
            'fact' => $fact
        ]);
    }

    public function activateWallet(Request $request)
    {
        $request->validate(['wallet_id' => 'required|integer|exists:wallets,id']);
        $vendor = $this->getActingVendor();
        if (!$vendor) return response()->json(['message' => 'Unauthenticated'], 401);

        $vw = \App\Models\VendorWallet::firstOrCreate(
            ['vendor_id' => $vendor->id, 'wallet_id' => $request->wallet_id],
            ['is_enabled' => true]
        );
        if (!$vw->is_enabled) { $vw->is_enabled = true; $vw->save(); }
        return response()->json(['message' => 'Activated', 'vendor_wallet_id' => $vw->id]);
    }

    public function deactivateWallet(Request $request)
    {
        $request->validate(['wallet_id' => 'required|integer|exists:wallets,id']);
        $vendor = $this->getActingVendor();
        if (!$vendor) return response()->json(['message' => 'Unauthenticated'], 401);

        $vw = \App\Models\VendorWallet::where('vendor_id', $vendor->id)->where('wallet_id', $request->wallet_id)->first();
        if (!$vw) {
            return response()->json(['message' => 'Not activated'], 404);
        }
        $vw->is_enabled = false;
        $vw->save();
        return response()->json(['message' => 'Deactivated']);
    }

}
