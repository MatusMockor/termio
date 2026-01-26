<?php

declare(strict_types=1);

namespace App\Contracts\Subscription;

use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

interface SubscriptionCreationStrategy
{
    /**
     * Check if this strategy supports the given plan.
     */
    public function supports(Plan $plan): bool;

    /**
     * Create a subscription for the given tenant and plan.
     */
    public function create(CreateSubscriptionDTO $dto, Tenant $tenant, Plan $plan): Subscription;
}
