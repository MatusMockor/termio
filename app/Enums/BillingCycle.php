<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [self::Monthly->value, self::Yearly->value];
    }
}
