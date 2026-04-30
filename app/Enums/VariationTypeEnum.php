<?php

namespace App\Enums;

enum VariationTypeEnum: int
{
    case MULTIPLE = 1;
    case SINGLE = 2;

    /**
     * Get the label for the enum value
     */
    public function label(): string
    {
        return match($this) {
            self::MULTIPLE => 'Несколько вариантов',
            self::SINGLE => 'Один вариант',
        };
    }

    /**
     * Get the type name (multi or single)
     */
    public function typeName(): string
    {
        return match($this) {
            self::MULTIPLE => 'multiple',
            self::SINGLE => 'single',
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

