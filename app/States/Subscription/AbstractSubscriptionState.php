<?php

declare(strict_types=1);

namespace App\States\Subscription;

use App\Contracts\States\SubscriptionState;
use App\Models\Subscription;

/**
 * Abstract base class for subscription states.
 *
 * Provides default implementations returning false for all capability methods.
 * Concrete states override only the methods that should return true.
 */
abstract class AbstractSubscriptionState implements SubscriptionState
{
    public function __construct(
        protected readonly Subscription $subscription
    ) {}

    public function canUpgrade(): bool
    {
        return false;
    }

    public function canDowngrade(): bool
    {
        return false;
    }

    public function canCancel(): bool
    {
        return false;
    }

    public function canResume(): bool
    {
        return false;
    }

    abstract public function getDisplayName(): string;

    abstract public function getDescription(): string;

    /**
     * @return array<int, string>
     */
    abstract public function getAllowedActions(): array;
}
