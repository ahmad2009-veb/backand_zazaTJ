<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'vendor_wallet_id', 'order_id', 'receipt_id', 'transaction_id', 'amount', 'status', 'reference', 'meta', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'meta' => 'array',
        'paid_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorWallet()
    {
        return $this->belongsTo(VendorWallet::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }
}

