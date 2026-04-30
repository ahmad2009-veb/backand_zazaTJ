<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDeliveryAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'original_quantity',
        'new_quantity',
        'action',
        'reason',
        'actor_id',
        'actor_role',
        'logged_at',
    ];

    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor()
    {
        return $this->belongsTo(\App\Models\Vendor::class, 'actor_id');
    }

    public function courier()
    {
        return $this->belongsTo(\App\Models\DeliveryMan::class, 'actor_id');
    }

    public function admin()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'actor_id');
    }

    public function vendorEmployee()
    {
        return $this->belongsTo(\App\Models\VendorEmployee::class, 'actor_id');
    }

    public function getActorAttribute()
    {
        return match ($this->actor_role) {
            'vendor' => $this->vendor,
            'courier' => $this->courier,
            'admin' => $this->admin,
            'vendor_employee' => $this->vendorEmployee,
            default => null,
        };
    }
}
