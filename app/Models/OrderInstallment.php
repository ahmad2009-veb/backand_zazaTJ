<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'external_transfer_id',
        'initial_payment',
        'total_due',
        'remaining_balance',
        'due_date',
        'is_paid',
        'paid_at',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'initial_payment' => 'decimal:2',
        'total_due' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function externalTransfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'external_transfer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(OrderInstallmentPayment::class);
    }
}
