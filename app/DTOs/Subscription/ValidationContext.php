<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

final readonly class ValidationContext
{
    public function __construct(
        public ?Subscription $subscription,
        public ?Plan $plan,
        public ?Tenant $tenant,
        public ?int $subscriptionId = null,
        public ?int $planId = null,
    ) {}

    public static function forDowngrade(
        ?Subscription $subscription,
        ?Plan $newPlan,
        ?int $subscriptionId = null,
        ?int $planId = null,
    ): self {
        return new self(
            subscription: $subscription,
            plan: $newPlan,
            tenant: $subscription?->tenant,
            subscriptionId: $subscriptionId,
            planId: $planId,
        );
    }

    public static function forUpgrade(
        ?Subscription $subscription,
        ?Plan $newPlan,
        ?int $subscriptionId = null,
        ?int $planId = null,
    ): self {
        return new self(
            subscription: $subscription,
            plan: $newPlan,
            tenant: $subscription?->tenant,
            subscriptionId: $subscriptionId,
            planId: $planId,
        );
    }
}
