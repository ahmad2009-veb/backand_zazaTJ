<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerSearchResource extends JsonResource
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

        $totalOrderCount = $this->orders()->whereNotIn('order_status', ['refunded', 'canceled'])->count();

        // Add customerImport data if exists
        if ($this->customerImports && $this->customerImports->isNotEmpty()) {
            $totalOrderAmount += $this->customerImports->sum('total_order_price');
            $totalOrderCount += $this->customerImports->sum('total_order_count');
        }

        $absenceDays = $this->calculateAbsenceDays();

        return [
            'id' => $this->id,
            'user_number' => $this->user_number,
            'name' => $this->name,
            'phone' => $this->phone,
            'default_address' => $this->user_address,
            'source' => $this->source,
            'birth_date' => $this->birth_date,
            'addresses' => CustomerAddressResource::collection($this->addresses),
            'total_order_amount' => $totalOrderAmount,
            'total_order_count' => $totalOrderCount,
            'absence_days' => $absenceDays,
            'loyalty_point' => $this->loyalty_points ?? 0,
            'loyalty_point_percentage' => $this->loyalty_points_percentage ?? 0
        ];
    }

    /**
     * Calculate days since last order
     */
    private function calculateAbsenceDays(): ?int
    {
        $lastOrder = $this->orders->sortByDesc('created_at')->first();

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
            return null; // No orders yet
        }

        $today = \Carbon\Carbon::now();

        return $today->diffInDays($lastOrderDate);
    }
}
