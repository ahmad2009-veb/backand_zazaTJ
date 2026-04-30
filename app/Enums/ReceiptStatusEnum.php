<?php

namespace App\Enums;

enum ReceiptStatusEnum: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';

    public static function labels(): array
    {
        return [
            self::PENDING->value => 'Черновик',
            self::COMPLETED->value => 'Проведен',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}

