<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscription\SubscriptionStrategyResolver;

final class SubscriptionCreateAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly SubscriptionStrategyResolver $strategyResolver,
    ) {}

    public function handle(CreateSubscriptionDTO $dto, Tenant $tenant): Subscription
    {
        $existingSubscription = $this->subscriptions->findActiveByTenant($tenant);

        if ($existingSubscription) {
            throw SubscriptionException::alreadySubscribed();
        }

        $plan = $this->plans->findById($dto->planId);

        if (! $plan) {
            throw SubscriptionException::planNotFound($dto->planId);
        }

        return $this->strategyResolver->resolve($plan)->create($dto, $tenant, $plan);
    }
}
