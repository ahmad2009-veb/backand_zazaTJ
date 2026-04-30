<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Traits\HasVendorNumbering;
use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory, HasVendorNumbering;

    protected $fillable = [
        'name',
        'amount',
        'transaction_category_id',
        'vendor_id',
        'admin_id',
        'description',
        'type',
        'status',
        'sale_id',
        'admin_id',
        'transaction_number'
    ];

    protected $casts = [
        'status' => TransactionStatusEnum::class,
    ];

    public function scopeFilter(Builder $query , Request $request)
    {
        $search = $request->search;
        $type = $request->type;
        $transaction_category_id = $request->transaction_category_id;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $requestedDate = $request->requestedDate;
        
        if ($date_from) {
            $date_from = Carbon::parse($date_from)->format('Y-m-d');
        }
        if ($date_to) {
            $date_to = Carbon::parse($date_to)->format('Y-m-d');
        }

        if ($requestedDate){
            $requestedDate = Carbon::parse($requestedDate)->format('Y-m-d');
        }

        return $query
            ->when($search, function (Builder $q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            })
            ->when($type, function (Builder $q) use ($type) {
                $q->where('type', $type);
            })
            ->when($transaction_category_id, function (Builder $q) use ($transaction_category_id) {
                $q->where('transaction_category_id', $transaction_category_id);
            })
            ->when($requestedDate, function (Builder $q) use ($requestedDate) {
                $q->whereDate('created_at', '=', $requestedDate)->where('status', TransactionStatusEnum::SUCCESS)->where('type', TransactionTypeEnum::INCOME);
            })
            ->when($date_from, function (Builder $q) use ($date_from) {
                $q->whereDate('created_at', '>=', $date_from);
            })
            ->when($date_to, function (Builder $q) use ($date_to) {
                $q->whereDate('created_at', '<=', $date_to);
            });
    }

    public function scopeSuccess (Builder $query) {
            $query->where('status', 'success');
    }

    public function scopeIncome(Builder $query) {
        $query->where('type', 'income');
    }

    public function categoryTransaction() {
        return $this->belongsTo(TransactionCategory::class , 'transaction_category_id');
    }

    public function sale() {
        return $this->belongsTo(Sale::class);
    }

    public function vendorWalletTransactions() {
        return $this->hasMany(VendorWalletTransaction::class);
    }

    /**
     * Get the number field name for vendor numbering
     */
    public function getNumberField(): string
    {
        return 'transaction_number';
    }

    /**
     * Get the display prefix for formatted ID
     */
    public function getDisplayPrefix(): string
    {
        return 'TXN';
    }
}
