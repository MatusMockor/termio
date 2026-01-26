<?php

declare(strict_types=1);

namespace App\States\Subscription;

/**
 * State representing a canceled subscription.
 *
 * Canceled subscriptions can only be resumed if still in grace period.
 * After grace period ends, users must resubscribe.
 */
final class CanceledState extends AbstractSubscriptionState
{
    public function canResume(): bool
    {
        return $this->subscription->onGracePeriod();
    }

    public function getDisplayName(): string
    {
        return 'Canceled';
    }

    public function getDescription(): string
    {
        if (! $this->subscription->ends_at) {
            return 'Subscription is canceled';
        }

        if (! $this->subscription->onGracePeriod()) {
            return 'Subscription has ended';
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays(
            $this->subscription->ends_at->startOfDay(),
            false
        );

        if ($daysLeft <= 0) {
            return 'Subscription has ended';
        }

        if ($daysLeft === 1) {
            return 'Subscription ends in 1 day';
        }

        return "Subscription ends in {$daysLeft} days";
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedActions(): array
    {
        if ($this->subscription->onGracePeriod()) {
            return ['resume'];
        }

        return ['resubscribe'];
    }
}
