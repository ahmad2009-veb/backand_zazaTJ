<?php

namespace App\Enums;

enum TransactionCycleTypeEnum: string
{
    case ONE_TIME = 'one_time';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';

    /**
     * Get the label for the enum value
     */
    public function label(): string
    {
        return match($this) {
            self::ONE_TIME => 'Единоразово',
            self::WEEKLY => 'Каждую неделю',
            self::MONTHLY => 'Ежемесячно',
        };
    }

    /**
     * Get all types as array for API response
     */
    public static function toArray(): array
    {
        return collect(self::cases())->map(function ($case) {
            return [
                'value' => $case->value,
                'label' => $case->label()
            ];
        })->toArray();
    }

    /**
     * Get all values
     */
    public static function values(): array
    {
        return collect(self::cases())->pluck('value')->toArray();
    }
}
