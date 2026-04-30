<?php

namespace App\Enums;


enum TransactionTypeEnum: string
{
    case INCOME   = 'income';
    case EXPENSE = 'expense';
    case DIVIDENDS = 'dividends';
    
    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Доход',
            self::EXPENSE => 'Расход',
            self::DIVIDENDS => 'Дивиденды',
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
