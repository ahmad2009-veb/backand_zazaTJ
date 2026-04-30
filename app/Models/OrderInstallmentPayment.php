<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderInstallmentPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_installment_id',
        'amount',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function installment()
    {
        return $this->belongsTo(OrderInstallment::class, 'order_installment_id');
    }
}
