<?php

declare(strict_types=1);

namespace App\States\Subscription;

/**
 * State representing a subscription in trial period.
 *
 * Trial subscriptions can be upgraded or canceled, but not downgraded.
 */
final class TrialingState extends AbstractSubscriptionState
{
    public function canUpgrade(): bool
    {
        return true;
    }

    public function canCancel(): bool
    {
        return true;
    }

    public function getDisplayName(): string
    {
        return 'On Trial';
    }

    public function getDescription(): string
    {
        if (! $this->subscription->trial_ends_at) {
            return 'Your trial is active';
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays(
            $this->subscription->trial_ends_at->startOfDay(),
            false
        );

        if ($daysLeft <= 0) {
            return 'Your trial has ended';
        }

        if ($daysLeft === 1) {
            return 'Trial ends in 1 day';
        }

        return "Trial ends in {$daysLeft} days";
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedActions(): array
    {
        return ['upgrade', 'cancel'];
    }
}
