<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Plan;
use Exception;

final class SubscriptionException extends Exception
{
    /**
     * @var array<string, array{current: int, limit: int}>
     */
    private array $violations = [];

    public static function planNotFound(int $planId): self
    {
        return new self("Plan with ID {$planId} not found.");
    }

    public static function subscriptionNotFound(int $subscriptionId): self
    {
        return new self("Subscription with ID {$subscriptionId} not found.");
    }

    public static function cannotUpgrade(Plan $from, Plan $to): self
    {
        return new self("Cannot upgrade from {$from->name} to {$to->name}.");
    }

    public static function cannotDowngrade(Plan $from, Plan $to): self
    {
        return new self("Cannot downgrade from {$from->name} to {$to->name}.");
    }

    /**
     * @param  array<string, array{current: int, limit: int}>  $violations
     */
    public static function usageExceedsLimits(array $violations): self
    {
        $messages = [];
        foreach ($violations as $resource => $data) {
            $messages[] = "{$resource}: {$data['current']} (limit: {$data['limit']})";
        }

        $exception = new self('Current usage exceeds new plan limits: '.implode(', ', $messages));
        $exception->violations = $violations;

        return $exception;
    }

    public static function alreadyCanceled(): self
    {
        return new self('Subscription is already canceled.');
    }

    public static function notCanceled(): self
    {
        return new self('Subscription is not canceled.');
    }

    public static function cancellationAlreadyEffective(): self
    {
        return new self('Cancellation has already taken effect.');
    }

    public static function noActiveSubscription(): self
    {
        return new self('No active subscription found.');
    }

    public static function alreadySubscribed(): self
    {
        return new self('Tenant already has an active subscription.');
    }

    public static function paymentMethodRequired(): self
    {
        return new self('Payment method is required for paid plans.');
    }

    public static function stripeError(string $message): self
    {
        return new self("Stripe error: {$message}");
    }

    /**
     * @return array<string, array{current: int, limit: int}>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
