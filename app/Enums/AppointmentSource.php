<?php

declare(strict_types=1);

namespace App\Enums;

enum AppointmentSource: string
{
    case Online = 'online';
    case Manual = 'manual';
    case Phone = 'phone';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
