<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'logo', 'is_available', 'vendor_id', 'type',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function vendorWallets()
    {
        return $this->hasMany(VendorWallet::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Scope to get only default (global) wallets
     */
    public function scopeDefault($query)
    {
        return $query->whereNull('vendor_id')->where('is_available', true);
    }

    /**
     * Scope to get vendor-specific custom wallets
     */
    public function scopeCustomForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}

