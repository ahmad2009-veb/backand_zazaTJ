<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseProduct extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'warehouse_product';

    protected $casts = [
        'cost_price' => 'float',
        'sale_price' => 'float',
        'quantity' => 'float',
        'wholesale_price' => 'float',
        'retail_price' => 'float',
        'wholesale_total_price' => 'float',
        'retail_total_price' => 'float',
        'attribute_id' => 'integer',
    ];

    protected static function booted() {}

    public function updateTotalPrices()
    {
        $this->wholesale_total_price = $this->wholesale_price * $this->quantity;
        $this->retail_total_price = $this->retail_price * $this->quantity;
        $this->save();
    }


    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function arrivals()
    {
        return $this->belongsToMany(Arrival::class, 'arrival_warehouse_products');
    }
}
