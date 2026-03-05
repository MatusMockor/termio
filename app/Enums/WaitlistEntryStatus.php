<?php

declare(strict_types=1);

namespace App\Enums;

enum WaitlistEntryStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
