<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class Admin extends Authenticatable
{
    use Notifiable, HasApiTokens;

    protected $fillable = [
        'f_name',
        'l_name',
        'email',
        'phone',
        'password',
        'role_id',
        'image',
        'status',
        'zone_id',
        'restaurant_id'
    ];

    protected $hidden = ['password', 'remember_token'];
    protected static function booted()
    {
        static::retrieved(function ($admin) {
            return  $admin->role;
        });
    }

    public function role()
    {
        return $this->belongsTo(AdminRole::class, 'role_id');
    }

    public function scopeZone($query)
    {
        if (isset(auth('admin')->user()->zone_id)) {
            return $query->where('zone_id', auth('admin')->user()->zone_id);
        }

        return $query;
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class, 'admin_id', 'id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function cashbox()
    {
        return $this->hasMany(Cashbox::class);
    }

    public function order_histories()
    {
        return $this->hasMany(OrderStatusHistory::class, 'admin_id', 'id');
    }

    public function transactionCategories()
    {
        return $this->hasMany(TransactionCategory::class, 'admin_id', 'id')->where('parent_id', 0);
    }
    public function transactionSubcategories()
    {
        return $this->hasMany(TransactionCategory::class, 'admin_id', 'id')->where('parent_id', '!=', 0);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
