<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $totalOrderAmount = $this->total_order_amount;
        $totalOrderCount  = $this->total_order_count;

        // Add customerImport data if exists
        if ($this->customerImports && $this->customerImports->isNotEmpty()) {
            $totalOrderAmount += $this->customerImports->sum('total_order_price');
            $totalOrderCount += $this->customerImports->sum('total_order_count');
        }

        $absenceDays = $this->calculateAbsenceDays();

        return [
            'id' => $this->id,
            'user_number' => $this->user_number,
            'f_name' => $this['f_name'],
            'l_name' => $this['l_name'],
            'order_count' => $this['order_count'],
            'phone' => $this['phone'],
            'source' => $this['source'],
            'birth_date' => $this['birth_date'],
            'default_address' => $this['user_address'],
            'created_by' => $this['created_by'],
            'addresses' => CustomerAddressResource::collection($this->addresses),
            'total_order_amount' => $totalOrderAmount,
            'total_order_count' => $totalOrderCount,
            'absence_days' => $absenceDays,
        ];
    }

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
            return null;
        }

        $lastOrderDate = \Carbon\Carbon::parse($lastOrderDate);
        $today = \Carbon\Carbon::now();

        return $today->diffInDays($lastOrderDate);
    }
}
