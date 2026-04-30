<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Resources\Json\JsonResource;

class VendorWalletTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $meta = $this->meta ?? [];
        $source = $meta['source'] ?? 'unknown';
        

        $description = $this->getTransactionDescription($source, $meta);
        
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'source' => $source,
            'description' => $description,
            'wallet' => [
                'id' => $this->vendorWallet?->wallet_id,
                'name' => $this->vendorWallet?->wallet?->name,
                'logo' => $this->getWalletLogo(),
                'type' => $this->vendorWallet?->wallet?->type ?? 'regular',
                'is_personal' => $this->vendorWallet?->wallet?->name === 'Личный',
            ],
            'order' => $this->when($this->order_id, [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
            ]),
            'transaction' => $this->when($this->transaction_id, [
                'id' => $this->transaction?->id,
                'name' => $this->transaction?->name,
                'type' => $this->transaction?->type,
            ]),
            'meta' => $meta,
        ];
    }

    private function getTransactionDescription($source, $meta)
    {
        switch ($source) {
            case 'order_store':
                $paymentType = $meta['payment_type'] ?? null;
                if ($paymentType === 'initial_payment') {
                    return 'Initial payment for installment order';
                } elseif ($paymentType === 'remaining_balance') {
                    return 'Remaining balance for installment order';
                }
                return 'Payment received for order';
            case 'order_successful':
                return 'Order payment confirmed';
            case 'installment_payment':
                return 'Installment payment received';
            case 'direct_transaction':
                $type = $meta['transaction_type'] ?? 'transaction';
                $name = $meta['transaction_name'] ?? 'Transaction';
                return ucfirst($type) . ': ' . $name;
            case 'wallet_transfer':
                $transferType = $meta['transfer_type'] ?? 'transfer';
                $description = $meta['description'] ?? 'Wallet transfer';
                return ucfirst($transferType) . ': ' . $description;
            default:
                return 'Wallet transaction';
        }
    }

    private function getWalletLogo()
    {
        $logo = $this->vendorWallet?->logo ?: $this->vendorWallet?->wallet?->logo;
        
        if ($logo && str_starts_with($logo, 'wallet_logos/')) {
            return 'storage/' . $logo;
        }
        
        return $logo;
    }
}
