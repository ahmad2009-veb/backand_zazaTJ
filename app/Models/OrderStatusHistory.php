<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use HasFactory;

    public $fillable = [
        'order_status',
        'order_id',
        'admin_id',
        'vendor_id',
        'comment',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }
}
