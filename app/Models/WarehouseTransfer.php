<?php

namespace App\Models;

use App\Enums\WarehouseTransferType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'to_vendor_id',
        'transfer_number',
        'name',
        'transfer_type',
        'status',
        'is_installment',
        'notes',
        'transferred_at',
        'received_at',
        'received_by',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
        'received_at' => 'datetime',
        'transfer_type' => WarehouseTransferType::class,
        'is_installment' => 'boolean',
    ];

    /**
     * Get the vendor that owns this transfer
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the source warehouse
     */
    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * Get the destination warehouse
     */
    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * Get the destination vendor (for external transfers)
     */
    public function toVendor()
    {
        return $this->belongsTo(Vendor::class, 'to_vendor_id');
    }

    /**
     * Get the vendor who received/accepted the transfer
     */
    public function receivedByVendor()
    {
        return $this->belongsTo(Vendor::class, 'received_by');
    }

    /**
     * Get all items in this transfer
     */
    public function items()
    {
        return $this->hasMany(WarehouseTransferItem::class);
    }

    /**
     * Get the receipt associated with this transfer (sender's outgoing receipt)
     */
    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }

    /**
     * Get the installment associated with this transfer
     */
    public function installment()
    {
        return $this->hasOne(OrderInstallment::class, 'external_transfer_id');
    }
    
    /**
     * Alias for backward compatibility
     */
    public function externalTransfer()
    {
        return $this->installment();
    }

    /**
     * Scope to filter by vendor
     */
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    /**
     * Scope to filter incoming transfers for a vendor (only pending acceptance)
     */
    public function scopeIncomingForVendor($query, $vendorId)
    {
        return $query->where('to_vendor_id', $vendorId)
            ->where('transfer_type', 'external')
            ->where('status', 'sent'); // Only show transfers waiting to be accepted
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if this is an external transfer
     */
    public function isExternal(): bool
    {
        return $this->transfer_type?->value === 'external';
    }
}

