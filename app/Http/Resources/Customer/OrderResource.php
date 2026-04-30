<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $order = $this;
        
        $orderQuantity = $order->details->sum('quantity');
        
        $totalDiscount = $order->details->sum(function ($detail) {
            $itemTotal = $detail->price * $detail->quantity;
            return ($itemTotal * $detail->discount) / 100;
        });
        
        $productImages = $order->details->map(function ($detail) {
            return $detail->product ? [
                'id' => $detail->product->id,
                'name' => $detail->product->name,
                'image' => $detail->product->image ? url('storage/product/' . $detail->product->image) : null,
                'quantity' => $detail->quantity,
            ] : null;
        })->filter()->values();

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'date' => \Carbon\Carbon::parse($order->created_at)->format('Y-m-d'),
            'order_amount' => $order->order_amount,
            'order_quantity' => $orderQuantity,
            'products' => $productImages,
            'discount' => $totalDiscount,
            'points' => $order->points_earned ?? 0,
        ];
    }
}
