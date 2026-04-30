<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantSearchCount extends Model
{
    use HasFactory;

    protected $table = 'restaurant_search_counts';
    protected $guarded = ['id'];
}
