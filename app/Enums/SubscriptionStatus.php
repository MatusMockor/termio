<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case Canceled = 'canceled';
    case PastDue = 'past_due';
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Unpaid = 'unpaid';
    case Paused = 'paused';

    /**
     * @return array<int, self>
     */
    public static function activeStatuses(): array
    {
        return [self::Active, self::Trialing];
    }

    /**
     * @return array<int, string>
     */
    public static function activeStatusValues(): array
    {
        return [self::Active->value, self::Trialing->value];
    }

    public function isActive(): bool
    {
        return in_array($this, self::activeStatuses(), true);
    }
}
