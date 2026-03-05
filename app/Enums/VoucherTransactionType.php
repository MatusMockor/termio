<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherTransactionType: string
{
    case Issue = 'issue';
    case Redeem = 'redeem';
    case Restore = 'restore';
    case Adjust = 'adjust';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
