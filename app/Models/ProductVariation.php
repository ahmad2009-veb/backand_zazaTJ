<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    use HasFactory;

    protected $table = 'product_variations';
    protected $guarded = ['id'];

    protected $casts = [
        'cost_price' => 'float',
        'sale_price' => 'float',
        'quantity' => 'float',
        'attribute_id' => 'integer',
    ];

    /**
     * Get the product that owns this variation
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get total value of this variation (quantity * cost_price)
     */
    public function getTotalCostAttribute(): float
    {
        return ($this->quantity ?? 0) * ($this->cost_price ?? 0);
    }

    /**
     * Get total revenue of this variation (quantity * sale_price)
     */
    public function getTotalRevenueAttribute(): float
    {
        return ($this->quantity ?? 0) * ($this->sale_price ?? 0);
    }

    /**
     * Get profit margin for this variation
     */
    public function getProfitMarginAttribute(): ?float
    {
        if (!$this->cost_price || $this->cost_price <= 0) {
            return null;
        }
        return round((($this->sale_price - $this->cost_price) / $this->cost_price) * 100, 2);
    }
}

