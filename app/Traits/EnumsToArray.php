<?php

namespace App\Traits;

trait EnumsToArray
{
    public static function toArray(): array
    {
        return array_map(fn(self $enum) => $enum->value, self::cases());
    }
}
