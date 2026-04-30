<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorCounterpartyType extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'value',
        'label',
    ];

    /**
     * Get the vendor that owns this custom type
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get all counterparties using this custom type
     */
    public function counterparties()
    {
        return $this->hasMany(Counterparty::class, 'custom_type_id');
    }
}
