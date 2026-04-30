<?php

namespace App\Http\Resources\Vendor;

use App\Enums\OrderStatusEnum;
use App\Http\Resources\Admin\MainRestaurantResource;
use App\Http\Resources\Admin\OrderShowFoodResource;
use App\Http\Resources\Admin\OrderShowProductResource;
use App\Http\Resources\Vendor\OrderInstallmentResource;

use Illuminate\Http\Resources\Json\JsonResource;

class VendorOrderShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $deliveryAddress = $this->delivery_address;
        $auditLogs = $this->deliveryAuditLogs
            ->sortByDesc('logged_at')
            ->groupBy('product_id')
            ->map(fn($logs) => $logs->first());
        OrderShowProductResource::setAuditLogs($auditLogs);
        if (is_string($deliveryAddress)) {
            $decoded = json_decode($deliveryAddress, true);

            // Use decoded JSON only if it's valid and actually an array
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $deliveryAddress = $decoded;
            }
        }
        // return [
        //     'id' => $this->id,
        //     'order_status' => $this->order_status,
        //     'customer' => [
        //         'id' => $this->customer ? $this->customer->id : 0,
        //         'f_name' => $this->customer ? $this->customer->f_name : $deliveryAddress['contact_person_name'] ?? '',
        //         'l_name' => $this->customer ? $this->customer->l_name : null,
        //         'phone' => $this->customer ? $this->customer->phone : $deliveryAddress['contact_person_number'] ?? '',
        //         'avatar' => $this->customer
        //             ? $this->customer->image !== null
        //                 ? url('storage/profile/' . $this->customer->image)
        //                 : null
        //             : null,
        //     ],
        //     'delivery_man' => $this->delivery_man ? [
        //         'id' => $this->delivery_man->id,
        //         'f_name' => $this->delivery_man->f_name,
        //         'l_name' => $this->delivery_man->l_name,
        //         'phone' => $this->delivery_man->phone,
        //         'avatar' => $this->delivery_man->image,
        //     ] : null,
        //     'delivery_address' => json_decode($this->delivery_address, true),
        //     'store' => [
        //         'id' => $this->store->id,
        //         'name' => $this->store->name,
        //         'phone' => $this->store->phone,
        //         'address' => $this->store->address,
        //         'logo' => $this->store->logo !== null ? url('storage/restaurant/' . $this->store->logo) : null,
        //     ],
        //     // 'main_restaurant' => MainRestaurantResource::make($this->restaurant->mainRestaurant),
        //     // 'zone_id' => $this->restaurant->zone_id,
        //     'products' => OrderShowProductResource::collection($this->details),
        //     'order_amount' => $this->order_amount,
        //     'coupon_discount_amount' => $this->coupon_discount_amount,
        //     'delivery_charge' => $this->delivery_charge,
        //     'created_at' => $this->created_at,
        // ];
      return [  'id' => $this->id,
        'order_number' => $this->order_number,
        'order_status' => $this->order_status,
        'customer' => [
            'id' => $this->customer ? $this->customer->id : 0,
            'user_number' => $this->customer ? $this->customer->user_number : null,
            'f_name' => $this->customer ? $this->customer->f_name : $deliveryAddress['contact_person_name'],
            'l_name' => $this->customer ? $this->customer->l_name : null,
            'phone' => $this->customer ? $this->customer->phone : $deliveryAddress['contact_person_number'],
            'avatar' => $this->customer
                ? $this->customer->image !== null
                    ? url('storage/profile/' . $this->customer->image)
                    : null
                : null,
            // Customer's current loyalty points balance (available to use)
            'loyalty_points' => $this->customer ? $this->customer->loyalty_points ?? 0 : 0,
            'loyalty_points_percentage' => $this->customer ? $this->customer->loyalty_points_percentage ?? 0 : 0,
            'has_loyalty_points' => $this->customer ? ($this->customer->loyalty_points_percentage > 0) : false,
        ],
        'delivery_man' => $this->delivery_man ? [
            'id' => $this->delivery_man->id,
            'f_name' => $this->delivery_man->f_name,
            'l_name' => $this->delivery_man->l_name,
            'phone' => $this->delivery_man->phone,
            'avatar' => $this->delivery_man->image ? url('storage/delivery-man/' . $this->delivery_man->image) :null ,
        ] : null,
        'delivery_address' => $deliveryAddress,
        'store' => $this->store ? [
            'id' => $this->store->id,
            'name' => $this->store->name,
            'phone' => $this->store->phone,
            'address' => $this->store->address,
            'logo' => $this->store->logo !== null ? url('storage/restaurant/' . $this->restaurant->logo) : null,
        ] : null,
        'warehouse' => $this->warehouse ? [
            'id' => $this->warehouse->id,
            'name' => $this->warehouse->name
        ] : [
            'id' => 9999,
            'name' => 'Центральный склад'
        ],
//            'main_restaurant' => MainRestaurantResource::make($this->restaurant->mainRestaurant),
        'zone_id' => $this->store?->zone_id,
        'products' => OrderShowProductResource::collection($this->details),
        'order_amount' => $this->order_amount,
        'coupon_discount_amount' => $this->coupon_discount_amount,
        'delivery_charge' => $this->delivery_charge,
        'created_at' => $this->created_at,
        'comment' => $this->comment,
        'comment_for_store' => $this->comment_for_store,
        'comment_for_warehouse' => $this->comment_for_warehouse,
        'installment' => $this->installment,
        'total_discount' => $this->total_discount,
        'details_total_price' => $this->details_total,

        // Loyalty Points (from store method)
        'points_used' => $this->points_used ?? 0,
        'points_earned' => $this->points_earned ?? 0,
        'points_will_earn' => $this->calculatePotentialPointsEarned(), // Dynamic calculation based on current order

        // Installment Information (from store method)
        'orderInstallment' => $this->whenLoaded('orderInstallment', function () {
            return OrderInstallmentResource::make($this->orderInstallment);
        }),
        'is_installment' => $this->order_status === OrderStatusEnum::INSTALLMENT,

        // Wallet Information (current active selections for editing)
        'wallets' => $this->whenLoaded('walletTransactions', function () {
            // Get only active wallet transactions (pending or success, not failed)
            $activeTransactions = $this->walletTransactions->whereIn('status', ['pending', 'success']);

            // Group by wallet to combine amounts if same wallet used multiple times
            $walletGroups = $activeTransactions->groupBy('vendor_wallet_id');

            return $walletGroups->map(function ($transactions, $vendorWalletId) {
                // Sum amounts for same wallet (in case of multiple payments to same wallet)
                $totalAmount = $transactions->sum('amount');
                $firstTransaction = $transactions->first();

                return [
                    'id' => $firstTransaction->vendorWallet?->wallet_id ?? $vendorWalletId,
                    'vendor_wallet_id' => $vendorWalletId,
                    'wallet_id' => $firstTransaction->vendorWallet?->wallet_id,
                    'name' => $firstTransaction->vendorWallet?->wallet?->name ?? 'Unknown Wallet',
                    'logo' => $firstTransaction->vendorWallet?->wallet?->logo ?? 'assets/wallet_icons/wallet.png',
                    'amount' => $totalAmount,
                    'status' => $firstTransaction->status,
                    'payment_type' => $firstTransaction->meta['payment_type'] ?? 'full_payment',
                    'paid_at' => $firstTransaction->paid_at,
                ];
            })->values(); // Reset array keys
        }),

        // Financial Summary (for editing calculations)
        'financial_summary' => [
            'order_amount' => $this->order_amount,
            'total_products_price' => $this->details->sum(function($detail) {
                return $detail->price * $detail->quantity;
            }),
            'delivery_charge' => $this->delivery_charge,
            'coupon_discount_amount' => $this->coupon_discount_amount,
            'points_used' => $this->points_used ?? 0,
            'payable_amount' => ($this->order_amount ?? 0) - ($this->points_used ?? 0),
        ],

        // Order Metadata (from store method)
        'delivery_type' => $this->delivery_type ?? 'standard',
        'order_note' => $this->order_note,
        'schedule_at' => $this->schedule_at,
        'scheduled' => $this->scheduled,
        'source' => $this->source ?? 'dashboard',
        'stock_deducted' => $this->stock_deducted ?? false,
    ];
    }

    /**
     * Calculate potential points that would be earned for this order
     * Based on current order amount and customer's loyalty percentage
     */
    private function calculatePotentialPointsEarned(): float
    {
        $customer = $this->customer;
        if (!$customer || $customer->loyalty_points_percentage <= 0) {
            return 0;
        }

        // Use same formula as LoyaltyPointsService
        // Formula: (order_amount + delivery_charge - points_used) * percentage / 100
        $finalAmount = $this->order_amount + ($this->delivery_charge ?? 0) - ($this->points_used ?? 0);
        $pointsToAward = ($finalAmount * $customer->loyalty_points_percentage) / 100;

        return round($pointsToAward, 2);
    }

}
