<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_transfer_id',
        'product_id',
        'product_variation_id',
        'quantity',
        'sale_price',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];

    /**
     * Get the warehouse transfer this item belongs to
     */
    public function warehouseTransfer()
    {
        return $this->belongsTo(WarehouseTransfer::class);
    }

    /**
     * Get the product for this item
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product variation if applicable
     */
    public function productVariation()
    {
        return $this->belongsTo(ProductVariation::class);
    }
}

