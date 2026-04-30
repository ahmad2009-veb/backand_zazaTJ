<?php

namespace App\Models;

use App\Enums\CounterpartyTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Counterparty extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'vendor_reference_id',
        'counterparty',
        'name',
        'address',
        'requisite',
        'phone',
        'type',
        'custom_type_id',
        'balance',
        'notes',
        'status',
        'photo',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'type' => CounterpartyTypeEnum::class,
    ];

    /**
     * Get the vendor that owns the counterparty
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the custom type (if using custom type instead of enum)
     */
    public function customType()
    {
        return $this->belongsTo(VendorCounterpartyType::class, 'custom_type_id');
    }

    /**
     * Get the referenced vendor (when this counterparty represents another vendor)
     */
    public function referencedVendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_reference_id');
    }

    /**
     * Get all receipts from this counterparty
     */
    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Check if this counterparty references a vendor
     */
    public function isVendorReference()
    {
        return !is_null($this->vendor_reference_id);
    }

    /**
     * Get the effective name (from referenced vendor's store if vendor_reference_id is set)
     */
    public function getEffectiveNameAttribute()
    {
        if ($this->isVendorReference() && $this->referencedVendor) {
            $store = $this->referencedVendor->store;
            return $store ? $store->name : ($this->referencedVendor->f_name . ' ' . ($this->referencedVendor->l_name ?? ''));
        }
        return $this->name;
    }

    /**
     * Get the effective counterparty field (from referenced vendor if vendor_reference_id is set)
     */
    public function getEffectiveCounterpartyAttribute()
    {
        if ($this->isVendorReference() && $this->referencedVendor) {
            return $this->referencedVendor->f_name . ' ' . ($this->referencedVendor->l_name ?? '');
        }
        return $this->counterparty;
    }

    /**
     * Get the effective address (from referenced vendor's store if vendor_reference_id is set)
     */
    public function getEffectiveAddressAttribute()
    {
        if ($this->isVendorReference() && $this->referencedVendor) {
            $store = $this->referencedVendor->store;
            return $store ? $store->address : null;
        }
        return $this->address;
    }

    /**
     * Get the effective phone (from referenced vendor if vendor_reference_id is set)
     */
    public function getEffectivePhoneAttribute()
    {
        if ($this->isVendorReference() && $this->referencedVendor) {
            return $this->referencedVendor->phone;
        }
        return $this->phone;
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
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by type (supports both enum and custom types)
     * Type can be an enum value or a custom type name
     */
    public function scopeOfType($query, $type)
    {
        $enumValues = CounterpartyTypeEnum::values();
        
        if (in_array($type, $enumValues)) {
            // It's an enum type
            return $query->where('type', $type)->whereNull('custom_type_id');
        } else {
            // It's a custom type - look up by name
            return $query->whereHas('customType', function($q) use ($type) {
                $q->where('value', $type);
            });
        }
    }

    /**
     * Get the photo URL
     */
    public function getPhotoUrlAttribute()
    {
        if (!$this->photo) {
            return null;
        }

        // If photo starts with 'counterparty_photos/', add storage prefix
        if (str_starts_with($this->photo, 'counterparty_photos/')) {
            return 'storage/' . $this->photo;
        }

        return $this->photo;
    }

    /**
     * Get available counterparty types (both default enum and custom types for a vendor)
     */
    public static function getTypes($vendorId = null)
    {
        $defaultTypes = CounterpartyTypeEnum::toArray();
        
        // If vendor ID is provided, also include custom types
        if ($vendorId) {
            $customTypes = VendorCounterpartyType::where('vendor_id', $vendorId)
                ->orderBy('value')
                ->get()
                ->map(function ($type) {
                    return [
                        'id' => $type->id, // ID for edit/delete operations
                        'value' => $type->value, // Use value (consistent with enum)
                        'label' => $type->label,
                        'is_custom' => true, // Custom types are editable
                    ];
                })
                ->toArray();
            
            return array_merge($defaultTypes, $customTypes);
        }
        
        return $defaultTypes;
    }

    /**
     * Get the effective type label (from enum or custom type)
     */
    public function getEffectiveTypeLabelAttribute()
    {
        if ($this->custom_type_id && $this->customType) {
            return $this->customType->label;
        }
        
        return $this->type->label();
    }

    /**
     * Get available statuses
     */
    public static function getStatuses()
    {
        return [
            'active' => 'Активный',
            'inactive' => 'Неактивный',
        ];
    }
}
