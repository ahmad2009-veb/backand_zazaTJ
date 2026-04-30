<?php


namespace App\Enums;

use App\Traits\EnumsToArray;

enum OrderStatusEnum: string
{
    use EnumsToArray;

    case ACCEPTED = 'accepted';
    // case CONFIRMED = 'confirmed';
    // case PENDING = 'pending';
    case PICKED_UP = 'picked_up';
    case DELIVERED = 'delivered';
    case POSTPONED = 'postponed';
    case CANCELED = 'canceled';
    case SUCCESSFUL = 'successful';
    case INSTALLMENT = 'installment';
    case REFUNDED = 'refunded';

    public  function label(): string
    {
        return match ($this) {

            self::ACCEPTED => 'Принято',
            // self::CONFIRMED => 'Подтверждено',
            // self::PENDING => 'В ожидании',
            self::PICKED_UP => 'На доставке',
            self::DELIVERED => 'Доставлено',
            self::POSTPONED => 'Перенесено',
            self::CANCELED => 'Отменено',
            self::REFUNDED => 'Возмещено',
            self::SUCCESSFUL => 'Успешно',
            self::INSTALLMENT => 'В рассрочку',
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
