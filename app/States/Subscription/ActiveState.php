<?php

declare(strict_types=1);

namespace App\States\Subscription;

/**
 * State representing an active subscription.
 *
 * Active subscriptions can be upgraded, downgraded, or canceled.
 */
final class ActiveState extends AbstractSubscriptionState
{
    public function canUpgrade(): bool
    {
        return true;
    }

    public function canDowngrade(): bool
    {
        return true;
    }

    public function canCancel(): bool
    {
        return true;
    }

    public function getDisplayName(): string
    {
        return 'Active';
    }

    public function getDescription(): string
    {
        return 'Your subscription is active';
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedActions(): array
    {
        return ['upgrade', 'downgrade', 'cancel', 'change_billing_cycle'];
    }
}
