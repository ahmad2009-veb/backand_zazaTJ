<?php

namespace App\Models;

use App\Enums\SaleStatusEnum;
use App\Events\ProductSold;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Sale extends Model
{
    use HasFactory;

    protected $casts = [
        'status' => SaleStatusEnum::class,
    ];
    protected $fillable = [
        'name',
        'status',
        'warehouse_id',
        'user_id',
        'vendor_id',
        'warehouse_transfer_id',
        'products_count',
        'total_price',
        'delivery_man_id',
        'delivery_charge'
    ];

    public static function booted()
    {
        static::updated(function ($sale) {


            Log::debug($sale);


            //Проверка если реализация проведена
            if ($sale->status === SaleStatusEnum::COMPLETED) {
                ProductSold::dispatch($sale);
            }
        });
    }


    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function saleProducts()
    {
        return $this->hasMany(SaleProduct::class);
    }

    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouseTransfer()
    {
        return $this->belongsTo(WarehouseTransfer::class);
    }
}
