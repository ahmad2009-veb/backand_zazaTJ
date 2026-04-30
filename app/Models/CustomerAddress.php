<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contact_person_name',
        'contact_person_number',
        'address_type',
        'address',
        'floor',
        'road',
        'house',
        'apartment',
        'domofon_code',
        'longitude',
        'latitude',
        'zone_id',
        'user_id',
    ];

    protected $casts = [
        'user_id'    => 'integer',
        'zone_id'    => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
