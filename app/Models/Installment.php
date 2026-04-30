<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Installment extends Model
{
    use HasFactory;

    protected $fillable = [
        'installmentable_id',
        'installmentable_type',
        'initial_payment',
        'total_due',
        'remaining_balance',
        'due_date',
        'is_paid',
        'paid_at',
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

    /**
     * Get the parent installmentable model (Order or WarehouseTransfer).
     */
    public function installmentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the vendor who created this installment
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'created_by');
    }

    /**
     * Get all payments for this installment
     * Note: InstallmentPayment model will be created when needed
     */
    // public function payments()
    // {
    //     return $this->hasMany(InstallmentPayment::class);
    // }
}
