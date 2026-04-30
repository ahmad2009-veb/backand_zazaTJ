<?php

namespace App\Enums;

enum CounterpartyTypeEnum: string
{
    case EMPLOYEE = 'employee';
    case CLIENT = 'client';
    case SUPPLIER = 'supplier';
    case INVESTOR = 'investor';
    case BANK = 'bank';
    case PARTNER = 'partner';
    case OTHER = 'other';

    /**
     * Get the label for the enum value
     */
    public function label(): string
    {
        return match($this) {
            self::EMPLOYEE => 'Сотрудник',
            self::CLIENT => 'Клиент',
            self::SUPPLIER => 'Поставщик',
            self::INVESTOR => 'Инвестор',
            self::BANK => 'Банк',
            self::PARTNER => 'Партнёр',
            self::OTHER => 'Прочее',
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
                'label' => $case->label(),
                'is_custom' => false, // System defaults are not custom
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
