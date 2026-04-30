<?php

namespace App\Models;

use App\Scopes\ZoneScope;
use App\Traits\HasVendorNumbering;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class DeliveryMan extends Authenticatable
{
    use Notifiable, HasVendorNumbering;

    protected $fillable = [
        'f_name',
        'l_name',
        'phone',
        'email',
        'identity_number',
        'identity_type',
        'identity_image',
        'image',
        'password',
        'auth_token',
        'fcm_token',
        'telegram_user_id',
        'zone_id',
        'restaurant_id',
        'store_id',
        'type',
        'status',
        'active',
        'available',
        'earning',
        'current_orders',
        'order_count',
        'assigned_order_count',
        'application_status',
        'delivery_man_number'
    ];

    protected $casts = [
        'zone_id' => 'integer',
        'status' => 'boolean',
        'active' => 'integer',
        'available' => 'integer',
        'earning' => 'float',
        'restaurant_id' => 'integer',
        'current_orders' => 'integer',
    ];

    protected $hidden = [
        'password',
        'auth_token',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
    }

    public function wallet()
    {
        return $this->hasOne(DeliveryManWallet::class);
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class, 'deliveryman_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function time_logs()
    {
        return $this->hasMany(TimeLog::class, 'user_id', 'id');
    }

    public function order_transaction()
    {
        return $this->hasMany(OrderTransaction::class);
    }

    public function todays_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereDate('created_at', now());
    }

    public function this_week_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereBetween('created_at',
            [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    public function this_month_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereMonth('created_at', date('m'))->whereYear('created_at',
            date('Y'));
    }

    public function todaysorders()
    {
        return $this->hasMany(Order::class)->whereDate('accepted', now());
    }

    public function this_week_orders()
    {
        return $this->hasMany(Order::class)->whereBetween('accepted',
            [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    public function delivery_history()
    {
        return $this->hasMany(DeliveryHistory::class, 'delivery_man_id');
    }

    public function last_location()
    {
        return $this->hasOne(DeliveryHistory::class, 'delivery_man_id')->latest();
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function reviews()
    {
        return $this->hasMany(DMReview::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function rating()
    {
        return $this->hasMany(DMReview::class)
            ->select(DB::raw('avg(rating) average, count(delivery_man_id) rating_count, delivery_man_id'))
            ->groupBy('delivery_man_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1)->where('application_status', 'approved');
    }

    public function scopeEarning($query)
    {
        return $query->where('earning', 1);
    }

    public function scopeAvailable($query)
    {
        return $query->where('current_orders', '<', config('dm_maximum_orders'));
    }

    public function scopeZonewise($query)
    {
        return $query->where('type', 'zone_wise');
    }

    public function filterOrdersByDuration($duration)
    {
        $query = $this->orders();
        switch ($duration) {
            case 'today':
                $query->whereDate('accepted', today());
                break;
            case 'week':
                $query->whereBetween('accepted', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereBetween('accepted', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case 'all_time':
                break;
            default:
                throw new \InvalidArgumentException('Invalid duration value');
        }

        return $query;
    }

    /**
     * Get the number field name for vendor numbering
     */
    public function getNumberField(): string
    {
        return 'delivery_man_number';
    }

    /**
     * Get the display prefix for formatted ID
     */
    public function getDisplayPrefix(): string
    {
        return 'DM';
    }
}
