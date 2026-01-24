<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

/**
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 */
final readonly class CreateSubscriptionDTO
{
    public function __construct(
        public int $tenantId,
        public int $planId,
        public string $billingCycle,
        public ?string $paymentMethodId = null,
        public bool $startTrial = true,
    ) {}
}
