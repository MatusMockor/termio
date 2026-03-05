<?php

declare(strict_types=1);

namespace App\Enums;

enum WaitlistEntrySource: string
{
    case Public = 'public';
    case Owner = 'owner';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
