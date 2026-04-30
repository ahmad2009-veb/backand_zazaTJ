<?php

namespace App\Models;

use App\Traits\HasVendorNumbering;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Models\CustomerImport;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasVendorNumbering;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'image',
        'f_name',
        'l_name',
        'phone',
        'user_address',
        'source',
        'loyalty_points_percentage',
        'loyalty_points',
        'email',
        'birth_date',
        'password',
        'status',
        'login_medium',
        'ref_code',
        'social_id',
        'is_phone_verified',
        'created_by',
        'user_number'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'interest',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_phone_verified' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'order_count' => 'integer',
        'wallet_balance' => 'float',
        'loyalty_point' => 'integer',
    ];



    public function getNameAttribute()
    {
        return trim($this->f_name . ' ' . $this->l_name);
    }

    public function getNameWithPhoneAttribute()
    {
        return trim("{$this->f_name} {$this->l_name} ({$this->phone})");
    }

    public function creator()
    {
        return $this->belongsTo(Store::class, 'created_by');
    }

    public function createdClients()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class, 'user_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function loyaltyPointTransactions()
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    /**
     * Check if customer has loyalty points enabled
     */
    public function hasLoyaltyPoints(): bool
    {
        return $this->loyalty_points_percentage > 0;
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function loyalty_point_transaction()
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    public function wallet_transaction()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(CustomerPoint::class, 'user_id', 'id');
    }



    public function campaigns(): HasMany
    {
        return $this->hasMany(UserCampaign::class, 'user_id', 'id');
    }

    public function deviceTokens(): HasMany {
        return $this->hasMany(UserDeviceToken::class, 'user_id', 'id');
    }

    public function customerImports(): HasMany
    {
        return $this->hasMany(CustomerImport::class);
    }

    /**
     * Get the number field name for vendor numbering
     */
    public function getNumberField(): string
    {
        return 'user_number';
    }

    /**
     * Get the vendor field name (users use created_by which references stores)
     */
    public function getVendorField(): string
    {
        return 'created_by';
    }

    /**
     * Get the display prefix for formatted ID
     */
    public function getDisplayPrefix(): string
    {
        return 'USER';
    }
}
