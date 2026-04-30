<?php

namespace App\Services;

use App\Models\OrderInstallment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FinanceService
{

    public function monthlyFinanceStatistics(string $type,  $user)
    {
        $monthlyIncome = DB::table('transactions')
            ->where($type . '_id', $user->id)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month_key, DATE_FORMAT(created_at, "%M, %Y") as month, SUM(amount) as total')
            ->where('type', 'income')
            ->where('status', 'success')
            ->groupBy('month_key', 'month')
            ->orderBy('month_key', 'DESC')
            ->get();

        $monthlyExpense =   DB::table('transactions')
            ->where($type . '_id', $user->id)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month_key, DATE_FORMAT(created_at, "%M, %Y") as month, SUM(amount) as total')
            ->where('type', 'expense')
            ->where('status', 'success')
            ->groupBy('month_key', 'month')
            ->orderBy('month_key', 'DESC')
            ->get();

        $expenseByMonth = $monthlyExpense->keyBy('month_key');

        $monthlyIncome->transform(function ($item) use ($expenseByMonth) {
            $monthlyExpense = $expenseByMonth[$item->month_key]->total ?? 0; // Get expense for the same month
            $profitability = $item->total - $monthlyExpense; // Calculate profitability per month
            Log::info($profitability);
            $item ->profitability = $profitability;
            $item->percentage = ($profitability > 0)
                ? round(($item->profitability / $item->total) * 100 , 2) // Percentage for this month
                : 0;

            return $item;
        });

        return [
            'income' => $monthlyIncome,
            'expense' => $monthlyExpense
        ];
    }

    public function mainIncomeStatistics(string $type,  $user)
    {
        $cacheKeyPrefix = $type . "_income_{$user->id}";
        $yearIncome = Cache::remember($cacheKeyPrefix . '_year',  3600, function () use ($user, $type) {
            return DB::table('transactions')
                ->where($type . '_id', $user->id)
                ->where('status', 'success')
                ->where('type', 'income')
                ->whereYear('created_at', now()->year)
                ->sum('amount');
        });

        $monthIncome = Cache::remember($cacheKeyPrefix . '_month', 3600, function () use ($user, $type) {
            return DB::table('transactions')
                ->where($type . '_id', $user->id)
                ->where('status', 'success')
                ->where('type', 'income')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');
        });

        $weekIncome  = Cache::remember($cacheKeyPrefix . '_week', 3600, function () use ($user, $type) {
            return DB::table('transactions')
                ->where($type . '_id', $user->id)
                ->where('status', 'success')
                ->where('type', 'income')
                ->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])
                ->whereYear('created_at', now()->year)
                ->sum('amount');
        });


        $todayIncome = Cache::remember($cacheKeyPrefix . '_today', 3600, function () use ($user, $type) {
            return DB::table('transactions')
                ->where($type . '_id', $user->id)
                ->where('status', 'success')
                ->where('type', 'income')
                ->whereDate('created_at', today())
                ->sum('amount');
        });

        return [
            'today_income' => $todayIncome,
            'week_income' => $weekIncome,
            'month_income' => $monthIncome,
            'year_income' => $yearIncome,
        ];
    }

    public function createInstallment(array $data, int $orderId, int $vendorId): void
    {
        if (!isset($data['is_installment']) || !$data['is_installment']) {
            return;
        }

        OrderInstallment::create([
            'order_id' => $orderId,
            'external_transfer_id' => null, // Null for orders
            'initial_payment' => $data['initial_payment'] ?? 0,
            'total_due' => $data['total_due'],
            'remaining_balance' => $data['remaining_balance'],
            'due_date' => $data['due_date'] ?? null,
            'is_paid' => false,
            'status' => null, // Null for regular order installments
            'notes' => $data['notes'] ?? null,
            'created_by' => $vendorId,
        ]);
    }

    public function updateInstallment(array $data, int $orderId, int $vendorId): void
    {
        if (!isset($data['is_installment']) || !$data['is_installment']) {
            OrderInstallment::where('order_id', $orderId)->delete();
            return;
        }

        $installment = OrderInstallment::where('order_id', $orderId)->first();

        if ($installment) {
            $installment->update([
                'initial_payment' => $data['initial_payment'] ?? $installment->initial_payment,
                'total_due' => $data['total_due'] ?? $installment->total_due,
                'remaining_balance' => $data['remaining_balance'] ?? $installment->remaining_balance,
                'due_date' => $data['due_date'] ?? $installment->due_date,
                'notes' => $data['comment'] ?? $installment->notes,
            ]);
        } else {
            $this->createInstallment($data, $orderId, $vendorId);
        }
    }
}
