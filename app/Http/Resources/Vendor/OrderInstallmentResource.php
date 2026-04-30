<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\OrderShowProductResource;
use App\Models\Order;

class OrderInstallmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id, // OrderInstallment doesn't have vendor numbering
            'initial_payment' => $this->initial_payment,
            'order_id' => $this->order ? $this->order->id : $this->order_id,
            'order_number' => $this->order ? $this->order->order_number : null,
            'created_at' => $this->created_at->toDateTimeString(),
            'customer' => optional($this->order->customer ?? null)->f_name ?? '—',
            'store' => optional($this->order->store ?? null)->name ?? '—',
            'product' => $this->order ? OrderShowProductResource::collection($this->order->details ?? collect([])) : [],
            'due_date' => $this->due_date?->toDateString(),
            'remaining_balance' => $this->remaining_balance,
            'is_paid' => $this->is_paid,
            'installment_type' => 'orders',
        ];
    }
}
