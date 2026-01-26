<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\States\SubscriptionState;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\States\Subscription\ActiveState;
use App\States\Subscription\CanceledState;
use App\States\Subscription\PastDueState;
use App\States\Subscription\TrialingState;
use RuntimeException;

/**
 * Factory for creating appropriate subscription state objects.
 *
 * Determines the correct state based on subscription properties:
 * - stripe_status
 * - trial_ends_at
 * - ends_at
 */
final class SubscriptionStateFactory
{
    /**
     * Create appropriate state object for subscription.
     *
     * State determination priority:
     * 1. Check if on trial (trial_ends_at is in the future)
     * 2. Check if canceled (ends_at is set)
     * 3. Check stripe_status for past_due
     * 4. Default to active state for active status
     *
     * @throws RuntimeException When subscription state cannot be determined
     */
    public function create(Subscription $subscription): SubscriptionState
    {
        // Trial takes precedence - subscription is on trial regardless of stripe_status
        if ($this->isOnTrial($subscription)) {
            return new TrialingState($subscription);
        }

        // Canceled state - ends_at is set (on grace period or ended)
        if ($this->isCanceled($subscription)) {
            return new CanceledState($subscription);
        }

        // Past due - payment failed
        if ($subscription->stripe_status === SubscriptionStatus::PastDue) {
            return new PastDueState($subscription);
        }

        // Active state - subscription is active
        if ($subscription->stripe_status === SubscriptionStatus::Active) {
            return new ActiveState($subscription);
        }

        // Handle incomplete and other statuses by treating them as active
        // to allow actions like upgrade or cancel
        if ($this->isIncompleteStatus($subscription)) {
            return new ActiveState($subscription);
        }

        throw new RuntimeException(
            "Unknown subscription state for subscription ID: {$subscription->id}"
        );
    }

    private function isOnTrial(Subscription $subscription): bool
    {
        if ($subscription->trial_ends_at === null) {
            return false;
        }

        return $subscription->trial_ends_at->isFuture();
    }

    private function isCanceled(Subscription $subscription): bool
    {
        return $subscription->ends_at !== null;
    }

    private function isIncompleteStatus(Subscription $subscription): bool
    {
        return in_array($subscription->stripe_status, [
            SubscriptionStatus::Incomplete,
            SubscriptionStatus::Trialing, // Trialing without trial_ends_at in future
            SubscriptionStatus::Unpaid,
            SubscriptionStatus::Paused,
        ], true);
    }
}
