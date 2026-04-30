<?php

namespace App\Enums;

enum TransactionStatusEnum: string
{
    case SUCCESS = 'success';
    case CANCELLED = 'cancelled';


    public  function label(): string
    {
        return match ($this) {

            self::SUCCESS => 'Успешно',
            self::CANCELLED => 'Отменено',
        };
    }

    public static function options()
    {
        return array_map(
            fn(self $el) => ['value' => $el->value, 'label' => $el->label()],
            self::cases(),
        );
    }
}
