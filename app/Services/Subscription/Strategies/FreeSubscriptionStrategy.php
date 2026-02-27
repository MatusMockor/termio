<?php

declare(strict_types=1);

namespace App\Services\Subscription\Strategies;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Subscription\SubscriptionCreationStrategy;
use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Enums\PlanSlug;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

final class FreeSubscriptionStrategy implements SubscriptionCreationStrategy
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    public function supports(Plan $plan): bool
    {
        return $plan->slug === PlanSlug::Free->value;
    }

    public function create(CreateSubscriptionDTO $dto, Tenant $tenant, Plan $plan): Subscription
    {
        return $this->subscriptions->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'type' => SubscriptionType::Default->value,
            'stripe_id' => 'free_'.$tenant->id,
            'stripe_status' => SubscriptionStatus::Active->value,
            'stripe_price' => null,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => null,
        ]);
    }
}
