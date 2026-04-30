<?php

namespace App\Http\Resources\Admin\Sale;

use App\Http\Resources\Admin\DeliveryManResource;
use App\Models\Sale;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Determine transfer info if this sale is associated with a warehouse transfer
        $transferType = null;
        $transferNumber = null;
        $warehouseTransfer = $this->warehouse_transfer_id ? $this->warehouseTransfer : null;

        if ($warehouseTransfer) {
            $transferType = $warehouseTransfer->transfer_type?->value ?? $warehouseTransfer->transfer_type;
            $transferNumber = $warehouseTransfer->transfer_number;
        }

        // Determine user/recipient name
        $userName = 'Неизвестный клиент';
        $userPhone = 'Неизвестный клиент';

        if ($this->user) {
            $userName = $this->user->name;
            $userPhone = $this->user->phone;
        } elseif ($warehouseTransfer && $warehouseTransfer->toVendor) {
            // For external transfers, show the destination vendor name with hint
            $vendor = $warehouseTransfer->toVendor;
            $vendorName = trim(($vendor->f_name ?? '') . ' ' . ($vendor->l_name ?? '')) ?: $vendor->f_name;
            $userName = $vendorName . ' (Продавец)';
            $userPhone = $vendor->phone ?? '-';
        }

        // Calculate total_price from sale products if stored value is 0
        $totalPrice = $this->total_price;
        if ($totalPrice == 0 && $this->relationLoaded('saleProducts')) {
            $totalPrice = $this->saleProducts->sum(fn($p) => ($p->price ?? 0) * ($p->quantity ?? 0));
        }

        // Get installment data if exists
        $installment = null;
        if ($this->order && $this->order->orderInstallment) {
            // Installment from order
            $installment = [
                'id' => $this->order->orderInstallment->id,
                'initial_payment' => $this->order->orderInstallment->initial_payment,
                'total_due' => $this->order->orderInstallment->total_due,
                'remaining_balance' => $this->order->orderInstallment->remaining_balance,
                'due_date' => $this->order->orderInstallment->due_date?->toDateString(),
                'is_paid' => $this->order->orderInstallment->is_paid,
                'status' => $this->order->orderInstallment->status,
                'notes' => $this->order->orderInstallment->notes,
            ];
        } elseif ($warehouseTransfer && $warehouseTransfer->installment) {
            // Installment from warehouse transfer
            $installment = [
                'id' => $warehouseTransfer->installment->id,
                'initial_payment' => $warehouseTransfer->installment->initial_payment,
                'total_due' => $warehouseTransfer->installment->total_due,
                'remaining_balance' => $warehouseTransfer->installment->remaining_balance,
                'due_date' => $warehouseTransfer->installment->due_date?->toDateString(),
                'is_paid' => $warehouseTransfer->installment->is_paid,
                'status' => $warehouseTransfer->installment->status,
                'notes' => $warehouseTransfer->installment->notes,
            ];
        }

        return [
            'id' => $this->id,
            'sale_name' => $this->name,
            'name' => $userName,
            'status' => $this->status,
            'warehouse' => $this->warehouse?->name,
            'order_id' => $this->order ? $this->order->id : $this->order_id,
            'order_number' => $this->computeOrderNumber(),
            'products_count' => $this->products_count,
            'total_price' => $totalPrice,
            'delivery_charge' => $this->delivery_charge,
            'phone' => $userPhone,
            'discount' => null,
            'products' => SaleProductResource::collection($this->whenLoaded('saleProducts')),
            'delivery_man' => DeliveryManResource::make($this->whenLoaded('delivery_man')),
            'warehouse_transfer_id' => $this->warehouse_transfer_id,
            'transfer_number' => $transferNumber,
            'transfer_type' => $transferType,
            'installment' => $installment,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Compute order number - real order number or ghost number for transfers
     */
    private function computeOrderNumber(): int
    {
        // If has real order, use its order_number
        if ($this->order_id && $this->order) {
            return $this->order->order_number;
        }

        // Find the last sale with a real order_number before this sale (by ID)
        $lastSaleWithOrder = Sale::where('id', '<', $this->id)
            ->whereNotNull('order_id')
            ->orderBy('id', 'desc')
            ->with('order')
            ->first();

        $lastRealOrderNumber = $lastSaleWithOrder?->order?->order_number ?? 0;
        $lastSaleId = $lastSaleWithOrder?->id ?? 0;

        // Count sales without orders between the last real order and this one
        $countSalesWithoutOrder = Sale::where('id', '>', $lastSaleId)
            ->where('id', '<=', $this->id)
            ->whereNull('order_id')
            ->count();

        return $lastRealOrderNumber + $countSalesWithoutOrder;
    }
}
