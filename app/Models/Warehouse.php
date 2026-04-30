<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'warehouses';

    protected $casts = [
        'status' => 'boolean'
    ];

    public function scopeCategories()
    {
        return $this->products->load('subCategory')
            ->map(fn(Product $product) => $product->subCategory)
            ->unique('id')
            ->values();
    }
    public function stores()
    {
        return $this->belongsToMany(Store::class, 'warehouse_store', 'warehouse_id', 'store_id');
    }

//    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
//    {
//        return $this->belongsToMany(Product::class, 'warehouse_product', 'warehouse_id', 'product_id');
//    }


    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class, 'warehouse_id' );
    }
    public function warehouseProducts(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(WarehouseProduct::class, 'warehouse_id');
    }

    public function scopeActive($query) {
        $query->where('status', 1);
    }


    public function owner() {
        return $this->belongsTo(WarehouseOwner::class,  'warehouse_owner_id');
    }

    public function arrivals() {
        return $this->hasMany(Arrival::class, 'warehouse_id');
    }
}
