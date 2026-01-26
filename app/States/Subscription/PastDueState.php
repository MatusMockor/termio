<?php

declare(strict_types=1);

namespace App\States\Subscription;

/**
 * State representing a subscription with failed payment.
 *
 * Past due subscriptions can only update payment method or cancel.
 */
final class PastDueState extends AbstractSubscriptionState
{
    public function canCancel(): bool
    {
        return true;
    }

    public function getDisplayName(): string
    {
        return 'Past Due';
    }

    public function getDescription(): string
    {
        return 'Payment failed. Please update your payment method.';
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedActions(): array
    {
        return ['update_payment_method', 'cancel'];
    }
}
