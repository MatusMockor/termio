<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Subscription\SubscriptionCreationStrategy;
use App\Models\Plan;
use RuntimeException;

final class SubscriptionStrategyResolver
{
    /**
     * @param  array<SubscriptionCreationStrategy>  $strategies
     */
    public function __construct(
        private readonly array $strategies,
    ) {}

    public function resolve(Plan $plan): SubscriptionCreationStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($plan)) {
                return $strategy;
            }
        }

        throw new RuntimeException('No strategy found for plan: '.$plan->slug);
    }
}
