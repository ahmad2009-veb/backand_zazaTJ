<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;


class Vendor extends Authenticatable
{
    use Notifiable, HasApiTokens, HasFactory;
    use \Staudenmeir\EloquentHasManyDeep\HasRelationships;
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'auth_token',
        'remember_token',
    ];

    public function employees()
    {
        return $this->hasMany(VendorEmployee::class);
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
        return $this->hasMany(OrderTransaction::class)->whereBetween(
            'created_at',
            [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
        );
    }

    public function this_month_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereMonth('created_at', date('m'))->whereYear(
            'created_at',
            date('Y')
        );
    }

    public function todaysorders()
    {
        return $this->hasManyThrough(Order::class, Restaurant::class)->whereDate('orders.created_at', now());
    }

    public function this_week_orders()
    {
        return $this->hasManyThrough(Order::class, Restaurant::class)->whereBetween(
            'orders.created_at',
            [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
        );
    }

    public function this_month_orders()
    {
        return $this->hasManyThrough(Order::class, Restaurant::class)->whereMonth(
            'orders.created_at',
            date('m')
        )->whereYear('orders.created_at', date('Y'));
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class, 'vendor_id', 'id');
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, Store::class);
    }

    public function restaurants()
    {
        return $this->hasMany(Restaurant::class);
    }

    public function withdrawrequests()
    {
        return $this->hasMany(WithdrawRequest::class);
    }

    public function wallet()
    {
        return $this->hasOne(RestaurantWallet::class);
    }

    public function store()
    {
        return $this->hasOne(Store::class);
    }

    public function warehouses()
    {
        return $this->hasManyThrough(
            Warehouse::class,
            Store::class,

        );
    }
    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            Store::class
        );
    }

    public function deliveryMans()

    {
        return $this->hasManyThrough(DeliveryMan::class, Store::class);
    }

    public function sales()
    {
        return $this->hasManyDeep(Sale::class, [Store::class, Warehouse::class]);
    }
    public function categories()
    {
        return $this->hasManyThrough(Category::class, Store::class);
    }

    public function attributes()
    {
        return $this->hasManyThrough(Attribute::class, Store::class);
    }

    public function transactionCategories()
    {
        return $this->hasMany(TransactionCategory::class, 'vendor_id', 'id')->where('parent_id', 0);
    }

    public function transactionSubcategories()
    {
        return $this->hasMany(TransactionCategory::class, 'vendor_id', 'id')->where('parent_id', '!=', 0);
    }

    public function transactions() {
        return $this->hasMany(Transaction::class);
    }

    public function createdInstallments()
    {
        return $this->hasMany(OrderInstallment::class, 'created_by');
    }
}
