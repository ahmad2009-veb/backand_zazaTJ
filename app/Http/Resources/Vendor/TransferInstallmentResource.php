<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\WarehouseTransfer;

class TransferInstallmentResource extends JsonResource
{
    public function toArray($request)
    {
        // Handle both OrderInstallment with external_transfer_id and legacy TransferInstallment
        $transfer = null;
        if ($this->externalTransfer) {
            // OrderInstallment with external_transfer_id
            $transfer = $this->externalTransfer;
        } elseif ($this->transfer) {
            // Legacy TransferInstallment
            $transfer = $this->transfer;
        }
        
        return [
            'id' => $this->id,
            'initial_payment' => $this->initial_payment,
            'transfer_id' => $transfer ? $transfer->id : ($this->external_transfer_id ?? $this->warehouse_transfer_id ?? null),
            'transfer_number' => $transfer ? $transfer->transfer_number : null,
            'created_at' => $this->created_at->toDateTimeString(),
            'vendor' => $transfer && $transfer->toVendor ? trim(($transfer->toVendor->f_name ?? '') . ' ' . ($transfer->toVendor->l_name ?? '')) : '—',
            'vendor_phone' => $transfer && $transfer->toVendor ? $transfer->toVendor->phone : '—',
            'warehouse' => $transfer && $transfer->fromWarehouse ? $transfer->fromWarehouse->name : '—',
            'products' => $transfer && $transfer->items ? $transfer->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'price' => $item->sale_price * $item->quantity,
                    'details' => [
                        'name' => $item->product->name ?? '—',
                        'image' => $item->product?->image !== null ? url('storage/product/' . $item->product->image) : null,
                    ],
                ];
            }) : [],
            'due_date' => $this->due_date?->toDateString(),
            'remaining_balance' => $this->remaining_balance,
            'is_paid' => $this->is_paid,
            'installment_type' => 'transfer',
        ];
    }
}
