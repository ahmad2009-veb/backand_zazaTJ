<?php

namespace App\Models;

use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model
{
    use HasFactory;

    protected $casts = [
        'amount' => 'float',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
