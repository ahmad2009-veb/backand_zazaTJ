<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class BusinessSetting extends Model
{
    public static function byKey(string $key): mixed
    {
        $settings = self::where('key', $key)->first();

        return json_decode(Arr::get($settings, 'value'), true);
    }
}
