<?php

namespace App\Models;

use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class Zone extends Model
{
    use HasFactory, HasSpatial;

    protected $casts = [
        'id'                      => 'integer',
        'status'                  => 'integer',
        'minimum_shipping_charge' => 'float',
        'per_km_shipping_charge'  => 'float',
        'coordinates' => Polygon::class,
    ];


    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
    }

    public function restaurants()
    {
        return $this->hasMany(Restaurant::class);
    }

    public function deliverymen()
    {
        return $this->hasMany(DeliveryMan::class);
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, Restaurant::class);
    }

    public function campaigns()
    {
        return $this->hasManyThrough(Campaign::class, Restaurant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }
}
