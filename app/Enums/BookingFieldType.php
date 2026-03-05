<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Date = 'date';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
