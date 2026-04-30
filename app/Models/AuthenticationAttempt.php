<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AuthenticationAttempt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'attempts',
        'phone',
        'expire',
        'code',
        'user_agent',
        'ip',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expire'   => 'datetime',
        'code'     => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            $model->user_agent = Str::limit($model->user_agent, 250);
        });
    }

    public function attemptsExceeded()
    {
        return $this->attempts >= 3;
    }
}
