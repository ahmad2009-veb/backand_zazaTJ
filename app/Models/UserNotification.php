<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    public $appends= ['order_status'];
    use HasFactory;

    public function getDataAttribute($value)
    {
        return json_decode($value, true);
    }

    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    public function getOrderStatusAttribute() {
        $orderId =  $this->data['order_id'];
        $order = Order::query()->where('id', $orderId)->first()?->order_status;
        return  $order;
    }
}
