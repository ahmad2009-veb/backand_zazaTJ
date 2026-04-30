<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_transfer_id',
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
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'created_by');
    }
}
