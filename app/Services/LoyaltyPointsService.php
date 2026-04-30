<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\LoyaltyPointTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoyaltyPointsService
{
    /**
     * Calculate and award points for an order
     */
    public function awardPointsForOrder(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) { return; }
        // Only award points if customer has loyalty points percentage > 0
        if ($customer->loyalty_points_percentage <= 0) {
            return;
        }

        // Calculate final amount for points calculation
        // Formula from client: total - discount - points_used + delivery
        // К оплате: 1-2-3+4
        // order_amount = products total after discount
        // We need to add delivery and subtract points used
        $finalAmount = $order->order_amount + ($order->delivery_charge ?? 0) - ($order->points_used ?? 0);

        // Calculate points to award
        $pointsToAward = ($finalAmount * $customer->loyalty_points_percentage) / 100;
        $pointsToAward = round($pointsToAward, 2);

        // Debug logging (remove in production)
        Log::info('Loyalty Points Calculation', [
            'customer_id' => $customer->id,
            'customer_percentage' => $customer->loyalty_points_percentage,
            'order_amount' => $order->order_amount,
            'delivery_charge' => $order->delivery_charge ?? 0,
            'points_used' => $order->points_used ?? 0,
            'final_amount' => $finalAmount,
            'points_to_award' => $pointsToAward,
        ]);

        if ($pointsToAward > 0) {
            DB::transaction(function () use ($customer, $order, $pointsToAward) {
                $balanceBefore = $customer->loyalty_points;
                $balanceAfter = $balanceBefore + $pointsToAward;

                // Update customer balance
                $customer->update(['loyalty_points' => $balanceAfter]);

                // Update order
                $order->update(['points_earned' => $pointsToAward]);

                // Create transaction record using existing table structure
                LoyaltyPointTransaction::create([
                    'user_id' => $customer->id,
                    'transaction_id' => $order->id,
                    'credit' => $pointsToAward,
                    'debit' => 0,
                    'balance' => $balanceAfter,
                    'reference' => 'order_payment',
                    'transaction_type' => 'Реализация',
                ]);
            });
        }
    }

    /**
     * Use points for an order
     */
    public function usePointsForOrder(Order $order, float $pointsToUse): bool
    {
        $customer = $order->customer;
        if (!$customer) { return false; }

        if ($customer->loyalty_points_percentage <= 0 || $pointsToUse <= 0) {
            return false;
        }

        if ($customer->loyalty_points < $pointsToUse) {
            return false; // Insufficient points
        }

        DB::transaction(function () use ($customer, $order, $pointsToUse) {
            $balanceBefore = $customer->loyalty_points;
            $balanceAfter = $balanceBefore - $pointsToUse;

            // Update customer balance
            $customer->update(['loyalty_points' => $balanceAfter]);

            // Update order
            $order->update(['points_used' => $pointsToUse]);

            // Create transaction record using existing table structure
            LoyaltyPointTransaction::create([
                'user_id' => $customer->id,
                'transaction_id' => $order->id,
                'credit' => 0,
                'debit' => $pointsToUse,
                'balance' => $balanceAfter,
                'reference' => 'order_payment',
                'transaction_type' => 'Points used for order #' . $order->id,
            ]);
        });

        return true;
    }

    /**
     * Manually add points to customer
     */
    public function addPointsManually(User $customer, float $points, string $reason, ?int $createdBy = null): void
    {
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($customer, $points, $reason, $createdBy) {
            $balanceBefore = $customer->loyalty_points;
            $balanceAfter = $balanceBefore + $points;

            // Update customer balance
            $customer->update(['loyalty_points' => $balanceAfter]);

            // Create transaction record using existing table structure
            LoyaltyPointTransaction::create([
                'user_id' => $customer->id,
                'transaction_id' => $createdBy,
                'credit' => $points,
                'debit' => 0,
                'balance' => $balanceAfter,
                'reference' => 'manual_adjustment',
                'transaction_type' => $reason,
            ]);
        });
    }

    /**
     * Manually subtract points from customer
     */
    public function subtractPointsManually(User $customer, float $points, string $reason, ?int $createdBy = null): bool
    {
        if ($points <= 0 || $customer->loyalty_points < $points) {
            return false;
        }

        DB::transaction(function () use ($customer, $points, $reason, $createdBy) {
            $balanceBefore = $customer->loyalty_points;
            $balanceAfter = $balanceBefore - $points;

            // Update customer balance
            $customer->update(['loyalty_points' => $balanceAfter]);

            // Create transaction record using existing table structure
            LoyaltyPointTransaction::create([
                'user_id' => $customer->id,
                'transaction_id' => $createdBy,
                'credit' => 0,
                'debit' => $points,
                'balance' => $balanceAfter,
                'reference' => 'manual_adjustment',
                'transaction_type' => $reason,
            ]);
        });

        return true;
    }

    /**
     * Update customer loyalty points percentage
     */
    public function updateCustomerLoyaltyPercentage(User $customer, float $percentage): void
    {
        $customer->update([
            'loyalty_points_percentage' => max(0, $percentage), // Ensure non-negative
        ]);
    }

    /**
     * Get customer loyalty points history with pagination
     */
    public function getCustomerPointsHistory(User $customer, int $perPage = 12)
    {
        return $customer->loyaltyPointTransactions()
                       ->orderBy('created_at', 'desc')
                       ->paginate($perPage);
    }

    /**
     * Get customer loyalty points summary
     */
    public function getCustomerLoyaltySummary(User $customer): array
    {
        return [
            'current_balance' => $customer->loyalty_points ?? 0,
            'loyalty_percentage' => $customer->loyalty_points_percentage ?? 0,
        ];
    }

    /**
     * Restore points from order (for order updates/cancellations)
     */
    public function restorePointsFromOrder(Order $order, float $pointsToRestore): bool
    {
        $customer = $order->customer;
        if (!$customer || $pointsToRestore <= 0) {
            return false;
        }

        DB::transaction(function () use ($customer, $order, $pointsToRestore) {
            $balanceBefore = $customer->loyalty_points;
            $balanceAfter = $balanceBefore + $pointsToRestore;

            // Update customer balance
            $customer->update(['loyalty_points' => $balanceAfter]);

            // Reset order points_used
            $order->update(['points_used' => 0]);

            // Create transaction record for restoration
            LoyaltyPointTransaction::create([
                'user_id' => $customer->id,
                'transaction_id' => $order->id,
                'credit' => $pointsToRestore,
                'debit' => 0,
                'balance' => $balanceAfter,
                'reference' => 'order_update',
                'transaction_type' => 'Points restored from order #' . $order->id,
            ]);
        });

        return true;
    }

    /**
     * Calculate potential points that would be earned for an order
     * This is used for order details to show how many points will be earned
     */
    public function calculatePotentialPointsEarned(Order $order): float
    {
        $customer = $order->customer;
        if (!$customer || $customer->loyalty_points_percentage <= 0) {
            return 0;
        }

        // Use same formula as awardPointsForOrder
        // Formula: (order_amount + delivery_charge - points_used) * percentage / 100
        $finalAmount = $order->order_amount + ($order->delivery_charge ?? 0) - ($order->points_used ?? 0);
        $pointsToAward = ($finalAmount * $customer->loyalty_points_percentage) / 100;

        return round($pointsToAward, 2);
    }

    /**
     * Recalculate and update points earned for an order (for order updates)
     * This removes old earned points and calculates new ones based on current order amount
     */
    public function recalculatePointsEarned(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer || $customer->loyalty_points_percentage <= 0) {
            // If customer doesn't earn points, reset to 0
            $order->update(['points_earned' => 0]);
            return;
        }

        $oldPointsEarned = $order->points_earned ?? 0;
        $newPointsEarned = $this->calculatePotentialPointsEarned($order);

        // Only update if there's a change
        if (abs($oldPointsEarned - $newPointsEarned) > 0.01) {
            DB::transaction(function () use ($customer, $order, $oldPointsEarned, $newPointsEarned) {
                // If there were old points earned, subtract them from customer balance
                if ($oldPointsEarned > 0) {
                    $customer->update(['loyalty_points' => $customer->loyalty_points - $oldPointsEarned]);

                    // Create reversal transaction
                    LoyaltyPointTransaction::create([
                        'user_id' => $customer->id,
                        'transaction_id' => $order->id,
                        'credit' => 0,
                        'debit' => $oldPointsEarned,
                        'balance' => $customer->loyalty_points,
                        'reference' => 'order_update',
                        'transaction_type' => 'Points recalculation reversal for order #' . $order->id,
                    ]);
                }

                // Add new points to customer balance
                if ($newPointsEarned > 0) {
                    $customer->update(['loyalty_points' => $customer->loyalty_points + $newPointsEarned]);

                    // Create new earning transaction
                    LoyaltyPointTransaction::create([
                        'user_id' => $customer->id,
                        'transaction_id' => $order->id,
                        'credit' => $newPointsEarned,
                        'debit' => 0,
                        'balance' => $customer->loyalty_points,
                        'reference' => 'order_update',
                        'transaction_type' => 'Реализация (Изменения заказа)',
                    ]);
                }

                // Update order with new points earned
                $order->update(['points_earned' => $newPointsEarned]);
            });
        }
    }
}
