<?php

namespace App\Enums;

enum WarehouseTransferType: string
{
    case INTERNAL = 'internal';
    case EXTERNAL = 'external';

    /**
     * Get the display name for the transfer type
     */
    public function label(): string
    {
        return match($this) {
            self::INTERNAL => 'Внутреннее перемещение',
            self::EXTERNAL => 'Внешнее перемещение',
        };
    }

    /**
     * Get all transfer types as array
     */
    public static function toArray(): array
    {
        return array_map(function ($case) {
            return [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }, self::cases());
    }
}

