<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArrivalWarehouseProduct extends Model
{
    use HasFactory;

    protected $table = 'arrival_warehouse_products';
    protected $guarded = ['id'];

    public function warehouseProduct()
    {
        return $this->belongsTo(WarehouseProduct::class, 'warehouse_product_id');
    }
}
