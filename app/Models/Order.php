<?php

namespace App\Models;

use App\Enums\OrderStatusEnum;
use App\Events\OrderStatusChanged;
use App\Scopes\ZoneScope;
use App\Traits\HasVendorNumbering;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    use HasFactory, HasVendorNumbering;

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int'; 


    public const DELIVERY = 'delivery';

    protected $casts = [
        'order_amount' => 'float',
        'coupon_discount_amount' => 'float',
        'total_tax_amount' => 'float',
        'restaurant_discount_amount' => 'float',
        'delivery_address_id' => 'integer',
        'delivery_man_id' => 'integer',
        'delivery_charge' => 'float',
        'original_delivery_charge' => 'float',
        'user_id' => 'integer',
        'scheduled' => 'integer',
        'restaurant_id' => 'integer',
        'details_count' => 'integer',
        'processing_time' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'dm_tips' => 'float',
        'refunded' => 'datetime',
        'order_status' => OrderStatusEnum::class,
        'points_used' => 'decimal:2',
        'points_earned' => 'decimal:2',
    ];
    protected $guarded = ['id'];

    /**
     * Get the number field name for vendor numbering
     */
    public function getNumberField(): string
    {
        return 'order_number';
    }

    /**
     * Get the vendor field name (orders use store_id, not vendor_id)
     */
    public function getVendorField(): string
    {
        return 'store_id';
    }

    /**
     * Get the display prefix for formatted ID
     */
    public function getDisplayPrefix(): string
    {
        return 'ORD';
    }


    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
        static::updating(function ($order) {
            event(new OrderStatusChanged($order));

        });
    }

    public function setDeliveryChargeAttribute($value)
    {
        $this->attributes['delivery_charge'] = round($value, 3);
    }

    public function getDetailsTotalAttribute()
    {
        return $this->details->sum('total');
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_code', 'code');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function delivery_history()
    {
        return $this->hasMany(DeliveryHistory::class, 'order_id');
    }

    public function walletTransactions()
    {
        return $this->hasMany(VendorWalletTransaction::class);
    }

    public function dm_last_location()
    {
        // return $this->hasOne(DeliveryHistory::class, 'order_id')->latest();
        return $this->delivery_man->last_location();
    }

    public function transaction()
    {
        return $this->hasOne(OrderTransaction::class);
    }

    public function scopeAccepteByDeliveryman($query)
    {
        return $query->where('order_status', 'accepted');
    }


    //check from here

    public function scopePreparing($query)
    {
        return $query->whereIn('order_status', ['processing', 'handover']);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('order_status', 'confirmed');
    }

    public function scopeOngoing($query)
    {
        return $query->whereIn('order_status', ['accepted', 'confirmed', 'processing', 'handover', 'picked_up']);
    }

    public function scopeFoodOnTheWay($query)
    {
        return $query->where('order_status', 'picked_up');
    }

    public function scopePending($query)
    {
        return $query->where('order_status', 'pending');
    }

    public function scopeRefundRequested($query)
    {
        return $query->where('order_status', 'refund_requested');
    }

    public function scopeFailed($query)
    {
        return $query->where('order_status', 'failed');
    }

    public function scopeCanceled($query)
    {
        return $query->where('order_status', 'canceled');
    }

    public function scopeDelivered($query)
    {
        return $query->where('order_status', 'delivered');
    }

    public function scopeRefunded($query)
    {
        return $query->where('order_status', 'refunded');
    }

    public function scopeSearchingForDeliveryman($query)
    {
        return $query->whereNull('delivery_man_id')->where('order_type', '=', 'delivery')->whereNotIn(
            'order_status',
            ['delivered', 'failed', 'canceled', 'refund_requested', 'refunded']
        );
    }

    public function scopeDelivery($query)
    {
        return $query->where('order_type', '=', 'delivery');
    }

    public function scopeScheduled($query)
    {
        return $query->whereRaw('created_at <> schedule_at')->where('scheduled', '1');
    }

    public function scopeOrderScheduledIn($query, $interval)
    {
        return $query->where(function ($query) use ($interval) {
            $query->whereRaw('created_at <> schedule_at')->where(function ($q) use ($interval) {
                $q->whereBetween(
                    'schedule_at',
                    [Carbon::now()->toDateTimeString(), Carbon::now()->addMinutes($interval)->toDateTimeString()]
                );
            })->orWhere('schedule_at', '<', Carbon::now()->toDateTimeString());
        })->orWhereRaw('created_at = schedule_at');
    }

    public function scopePos($query)
    {
        return $query->where('order_type', '=', 'pos');
    }

    public function scopeNotpos($query)
    {
        return $query->where('order_type', '<>', 'pos');
    }

    public function scopeNotStore(Builder $query)
    {
        $query->where('store_id', null);
    }

    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    public function customer_point()
    {
        return $this->hasOne(CustomerPoint::class);
    }

    public function cashbox()
    {
        return $this->hasOne(Cashbox::class, 'cashbox_id');
    }


    public function scopeFilterByDuration(Builder $query, $duration, $fromDate = null, $toDate = null)
    {
        if ($duration) {
            switch ($duration) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    break;
                case 'all_time':
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid duration value');
            }
        }


        if ($fromDate && $toDate) {

            $parsedFromDate = Carbon::parse($fromDate)->startOfDay();
            $parsedToDate = Carbon::parse($toDate)->endOfDay();

            // Ensure fromDate is not after toDate
            if ($parsedFromDate > $parsedToDate) {
                throw new \InvalidArgumentException('From date cannot be after to date');
            }

            $query->whereBetween('created_at', [$parsedFromDate, $parsedToDate]);
        }
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function  warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    public function sale()
    {
        return $this->hasOne(Sale::class);
    }

    public function orderInstallment()
    {
        return $this->hasOne(OrderInstallment::class);
    }

    public function getInstallmentAttribute()
    {
        return $this->orderInstallment->remaining_balance ?? 0;
    }

    public function deliveryAuditLogs()
    {
        return $this->hasMany(OrderDeliveryAuditLog::class);
    }

    public function scopeStatusStatistics(Builder $query, $status, $duration, $restaurantId = null)
    {
        if ($status && $duration) {
            $query->where('order_status', $status);
    
            if ($restaurantId) {
                $query->where('store_id', $restaurantId);
            }
    
            return $query
                ->when($duration == 'today', fn($q) => $q->whereDate('created_at', Carbon::today())) //In private methods there was a suspicious get method within this line ->get()
                ->when($duration == 'week', fn($q) => $q->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]))
                ->when($duration == 'month', fn($q) => $q->whereMonth('created_at', Carbon::now()->month))
                ->when($duration == 'year', fn($q) => $q->whereYear('created_at', Carbon::now()->year))
                ->when($duration == 'all_time', fn($q) => $q)
                ->count();
        }
    }

    public function getTotalDiscountAttribute(){
        return (int)$this->details()->sum('discount');
    }
}
