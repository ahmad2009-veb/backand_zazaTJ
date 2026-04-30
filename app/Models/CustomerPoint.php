<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPoint extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function order() {
        return $this->belongsTo(Order::class);
    }
    public function campaign() {
        return $this->belongsTo(Campaign::class);
    }

}
