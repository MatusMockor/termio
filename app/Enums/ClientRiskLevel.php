<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
