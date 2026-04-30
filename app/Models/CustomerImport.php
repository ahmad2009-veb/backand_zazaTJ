<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'purchase_date',
        'products',
        'total_order_price',
        'discount',
        'size',
        'total_order_count',
        'last_purchase_date',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'last_purchase_date' => 'date',
        'total_order_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_order_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
