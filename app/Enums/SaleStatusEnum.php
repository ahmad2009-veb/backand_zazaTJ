<?php

namespace App\Enums;

enum SaleStatusEnum: string
{
    case PENDING  = 'pending';
    case COMPLETED = 'completed';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Черновик',
            self::COMPLETED => 'Проведен',
            self::REFUNDED => 'Возврат',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn(self $condition) => ['value' => $condition->value, 'label' => $condition->label()],
            self::cases()
        );
    }
}
