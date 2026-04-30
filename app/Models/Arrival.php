<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Arrival extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function arrivalWarehouseProducts()
    {
        return $this->hasMany(ArrivalWarehouseProduct::class, 'arrival_id');
    }

    // public function warehouseProducts()
    // {
    //     return $this->hasManyThrough(
    //         WarehouseProduct::class,
    //         ArrivalWarehouseProduct::class,
    //         'arrival_id',
    //         'id',
    //         'id',
    //         'warehouse_product_id'

    //     );

    // }


    public function warehouseProducts()
    {
        return $this->belongsToMany(WarehouseProduct::class, 'arrival_warehouse_products');
    }
}
