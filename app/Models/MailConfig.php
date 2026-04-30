<?php

namespace App\Models;

use App\Scopes\RestaurantScope;
use Illuminate\Database\Eloquent\Model;

class MailConfig extends Model
{
    protected static function booted()
    {
        if (auth('vendor')->check()) {
            static::addGlobalScope(new RestaurantScope);
        }
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
