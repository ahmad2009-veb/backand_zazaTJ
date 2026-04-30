<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyPointTransactionResource extends JsonResource
{
    private static $lastCheckpoint = 0;

    public function toArray($request)
    {
        $order = $this->getRelatedOrder();
        
        $this->updateCheckpointIfNeeded();

        return [
            'id' => $this->id,
            'date' => $this->created_at->format('Y-m-d'),
            'balance' => self::$lastCheckpoint,
            'transaction_type' => $this->transaction_type,
            'points_used' => $this->getPointsUsed($order),
            'accrual' => $this->getPointsAccrual($order),
            'remaining' => $this->balance,
        ];
    }

    private function getRelatedOrder()
    {
        return $this->transaction_id ? \App\Models\Order::find($this->transaction_id) : null;
    }

    private function updateCheckpointIfNeeded(): void
    {
        if ($this->transaction_type === 'Реализация') {
            self::$lastCheckpoint = $this->balance;
        }
    }

    private function getPointsUsed($order): float
    {
        return $order ? ($order->points_used ?? 0) : ($this->debit ?? 0);
    }

    private function getPointsAccrual($order): float
    {
        return $order ? ($order->points_earned ?? 0) : ($this->credit ?? 0);
    }

    public static function resetCheckpoint(): void
    {
        self::$lastCheckpoint = 0;
    }
}
