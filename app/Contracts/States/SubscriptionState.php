<?php

declare(strict_types=1);

namespace App\Contracts\States;

/**
 * State interface for subscription status.
 *
 * Implements the State pattern to encapsulate subscription state-specific
 * behavior and allowed actions.
 */
interface SubscriptionState
{
    /**
     * Check if subscription can be upgraded in current state.
     */
    public function canUpgrade(): bool;

    /**
     * Check if subscription can be downgraded in current state.
     */
    public function canDowngrade(): bool;

    /**
     * Check if subscription can be canceled in current state.
     */
    public function canCancel(): bool;

    /**
     * Check if subscription can be resumed in current state.
     */
    public function canResume(): bool;

    /**
     * Get the human-readable display name for current state.
     */
    public function getDisplayName(): string;

    /**
     * Get the description explaining current state.
     */
    public function getDescription(): string;

    /**
     * Get list of allowed actions in current state.
     *
     * @return array<int, string>
     */
    public function getAllowedActions(): array;
}
