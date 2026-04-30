<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $casts = [
        'tax' => 'float',
        'price' => 'float',
        'status' => 'boolean',
        'discount' => 'float',
        'avg_rating' => 'float',
        'set_menu' => 'integer',
        'category_id' => 'integer',
        'restaurant_id' => 'integer',
        'reviews_count' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime',
        'veg' => 'integer',
        'purchase_price' => 'float',

    ];

    protected $guarded = ['id'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function orders()
    {
        return $this->hasMany(OrderDetail::class);
    }

    //    public function scopeActive($query)
    //    {
    //        return $query->where('status', 1)->whereHas('store', function ($query) {
    //            return $query->where('status', 1);
    //        });
    //    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_product', 'product_id', 'warehouse_id')
            ->withTimestamps();  // Optional
    }

    public function warehouseProducts()
    {
        return $this->hasMany(WarehouseProduct::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    
    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function receiptItems()
    {
        return $this->hasMany(ReceiptItem::class);
    }

    public function getTotalVariationQuantityAttribute(): float
    {
        return $this->variations()->sum('quantity') ?? 0;
    }

    // public function getAvgPurchasePriceAttribute(): ?float
    // {
    //     $items = $this->warehouseProducts()->select('quantity', 'purchase_price')->get();
    //     $totalQty = (float) $items->sum('quantity');
    //     if ($totalQty <= 0) {
    //         return $this->purchase_price !== null ? (float) $this->purchase_price : null;
    //     }
    //     $weightedSum = 0.0;
    //     foreach ($items as $i) {
    //         $weightedSum += ((float) $i->quantity) * ((float) $i->purchase_price);
    //     }
    //     return round($weightedSum / $totalQty, 2);
    // }

    public function getCostEffectivenessAttribute(): ?float
    {
        $avg = $this->purchase_price ?? null;
        $price = $this->price;
        if (!$avg || $avg <= 0 || !$price) {
            return null;
        }
        return round((($price - $avg) / $avg) * 100, 2);
    }
}

