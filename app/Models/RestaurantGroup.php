<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantGroup extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function restaurants() {
        return $this->hasMany(Restaurant::class);
    }
}
