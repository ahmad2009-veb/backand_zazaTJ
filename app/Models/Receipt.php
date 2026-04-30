<?php

namespace App\Models;

use App\Enums\ReceiptStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'warehouse_id',
        'counterparty_id',
        'recipient_vendor_id',
        'warehouse_transfer_id',
        'original_receipt_id',
        'receipt_number',
        'name',
        'status',
        'total_amount',
        'notes',
        'received_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'received_at' => 'datetime',
        'status' => ReceiptStatusEnum::class,
    ];

    /**
     * Get the vendor that owns the receipt
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the warehouse for this receipt
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the counterparty (supplier) for this receipt
     */
    public function counterparty()
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * Get the recipient vendor (for vendor-to-vendor transfers)
     */
    public function recipientVendor()
    {
        return $this->belongsTo(Vendor::class, 'recipient_vendor_id');
    }

    /**
     * Get the associated warehouse transfer
     */
    public function warehouseTransfer()
    {
        return $this->belongsTo(WarehouseTransfer::class);
    }

    /**
     * Get the original receipt that this receipt is refunding
     */
    public function originalReceipt()
    {
        return $this->belongsTo(Receipt::class, 'original_receipt_id');
    }

    /**
     * Get refund receipts for this receipt
     */
    public function refundReceipts()
    {
        return $this->hasMany(Receipt::class, 'original_receipt_id');
    }

    /**
     * Get all items in this receipt
     */
    public function items()
    {
        return $this->hasMany(ReceiptItem::class);
    }

    /**
     * Check if this receipt is for a vendor transfer (not counterparty)
     */
    public function isVendorTransfer(): bool
    {
        return !is_null($this->recipient_vendor_id);
    }

    /**
     * Get the recipient name (counterparty name or vendor name)
     */
    public function getRecipientNameAttribute(): ?string
    {
        if ($this->recipient_vendor_id) {
            return $this->recipientVendor?->name ?? $this->recipientVendor?->f_name;
        }
        return $this->counterparty?->name;
    }

    /**
     * Scope to filter by vendor
     */
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by counterparty type
     */
    public function scopeByCounterpartyType($query, $type)
    {
        return $query->whereHas('counterparty', function ($q) use ($type) {
            $q->where('type', $type);
        });
    }

    /**
     * Scope to filter vendor-to-vendor receipts
     */
    public function scopeVendorTransfers($query)
    {
        return $query->whereNotNull('recipient_vendor_id');
    }

    /**
     * Scope to filter counterparty receipts
     */
    public function scopeCounterpartyReceipts($query)
    {
        return $query->whereNotNull('counterparty_id');
    }
}

