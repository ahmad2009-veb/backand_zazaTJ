<?php

namespace App\Models;

use App\Enums\SaleStatusEnum;
use App\Events\ProductSold;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SaleProduct extends Model
{
    use HasFactory;

    protected $guarded = [
        'id'
    ];
    public static function booted()
    {
        static::created(function (SaleProduct $saleProduct) {
            //Проверка если реализация проведена
            if ($saleProduct->sale->status === SaleStatusEnum::COMPLETED) {
                ProductSold::dispatch($saleProduct);
            }
        });
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
