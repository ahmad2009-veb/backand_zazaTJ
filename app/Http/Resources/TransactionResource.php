<?php

namespace App\Http\Resources;

use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $amount = $this->amount;

        // Subtract points used from amount if this transaction has an associated order
        if ($this->sale && $this->sale->order && $this->sale->order->points_used > 0) {
            $amount = $this->amount - $this->sale->order->points_used;
        }

        // Get wallet information from vendor wallet transactions
        $wallet = null;
        if ($this->relationLoaded('vendorWalletTransactions')) {
            $vendorWalletTransaction = $this->vendorWalletTransactions->first();
            if ($vendorWalletTransaction && $vendorWalletTransaction->relationLoaded('vendorWallet') && $vendorWalletTransaction->vendorWallet) {
                if ($vendorWalletTransaction->vendorWallet->relationLoaded('wallet') && $vendorWalletTransaction->vendorWallet->wallet) {
                    $wallet = [
                        'id' => $vendorWalletTransaction->vendorWallet->wallet->id,
                        'name' => $vendorWalletTransaction->vendorWallet->wallet->name,
                    ];
                }
            }
        }

        // Get category and subcategory names
        $category = null;
        $subcategory = null;
        
        if ($this->relationLoaded('categoryTransaction') && $this->categoryTransaction) {
            if ($this->categoryTransaction->parent) {
                // This is a subcategory
                $category = [
                    'id' => $this->categoryTransaction->parent->id,
                    'name' => $this->categoryTransaction->parent->name,
                ];
                $subcategory = [
                    'id' => $this->categoryTransaction->id,
                    'name' => $this->categoryTransaction->name,
                ];
            } else {
                // This is a main category
                $category = [
                    'id' => $this->categoryTransaction->id,
                    'name' => $this->categoryTransaction->name,
                ];
                $subcategory = null;
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'wallet' => $wallet,
            'category' => $category,
            'subcategory' => $subcategory,
            'type' => $this->type,
            'transaction_number' => $this->transaction_number,
            'created_at' => $this->created_at,
            'amount' => $amount,
        ];
    }
}
