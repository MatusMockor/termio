<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

final readonly class ImmediateUpgradeSubscriptionDTO
{
    public function __construct(
        public int $subscriptionId,
        public int $newPlanId,
        public ?string $billingCycle = null,
    ) {}
}
