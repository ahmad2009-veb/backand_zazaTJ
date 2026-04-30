<?php

namespace App\Http\Resources\Customer;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\CustomerAddressResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $totalOrderAmount = $this->orders
                            ->whereNotIn('order_status', ['refunded', 'canceled'])
                            ->sum('order_amount');

        $totalOrderCount = $this->orders
                           ->whereNotIn('order_status', ['refunded', 'canceled'])
                           ->count();

        // Add customerImport data if exists
        if ($this->customerImports && $this->customerImports->isNotEmpty()) {
            $totalOrderAmount += $this->customerImports->sum('total_order_price');
            $totalOrderCount += $this->customerImports->sum('total_order_count');
        }

        $absenceDays = $this->calculateAbsenceDays();

        return [
            'id' => $this->id,                    // Real database ID (for backend use)
            'user_number' => $this->user_number,  // Vendor-specific number (for display)
            'customer_id' => $this->user_number,  // Alias for frontend compatibility
            'f_name' => $this->f_name,
            'l_name' => $this->l_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'source' => $this->source,
            'birth_date' => Carbon::parse($this->birth_date)->format('Y-m-d'),
            'user_address' => $this->user_address,
            'created_at' => $this->created_at,
            'addresses' => CustomerAddressResource::collection($this->addresses),
            'total_order_amount' => $totalOrderAmount,
            'total_order_count' => $totalOrderCount,
            'absence_days' => $absenceDays,
            'loyalty_points_percentage' => $this->loyalty_points_percentage,
            'loyalty_points' => $this->loyalty_points,
            'has_loyalty_points' => $this->loyalty_points_percentage > 0, // Computed field
        ];
    }

    /**
     * Calculate days since last order
     */
    private function calculateAbsenceDays(): ?int
    {
        $lastOrder = $this->orders
                          ->whereNotIn('order_status', ['refunded', 'canceled'])
                          ->sortByDesc('created_at')
                          ->first();

        $lastOrderDate = null;

        if ($lastOrder) {
            $lastOrderDate = \Carbon\Carbon::parse($lastOrder->created_at);
        }

        // Check customerImport for last purchase date if exists
        if ($this->customerImports && $this->customerImports->isNotEmpty()) {
            $lastImportPurchase = $this->customerImports
                                       ->sortByDesc('purchase_date')
                                       ->first();

            if ($lastImportPurchase && $lastImportPurchase->purchase_date) {
                $importDate = \Carbon\Carbon::parse($lastImportPurchase->purchase_date);

                // Use whichever is more recent
                if (!$lastOrderDate || $importDate->gt($lastOrderDate)) {
                    $lastOrderDate = $importDate;
                }
            }
        }

        if (!$lastOrderDate) {
            return null;
        }

        $today = \Carbon\Carbon::now();

        return $today->diffInDays($lastOrderDate);
    }
}
