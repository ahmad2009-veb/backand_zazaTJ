<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryManOtp extends Model
{
    protected $table = 'delivery_man_otps';

    protected $fillable = [
        'phone',
        'otp',
        'expires_at',
        'verified',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified'   => 'boolean',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
