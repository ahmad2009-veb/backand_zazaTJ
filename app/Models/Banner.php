<?php

namespace App\Models;

use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $casts = [
        'data' => 'integer',
        'status' => 'boolean'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'data');
    }
}
