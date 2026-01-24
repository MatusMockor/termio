<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Models\Plan;
use App\Models\Tenant;
use Carbon\Carbon;
use RuntimeException;

final class SubscriptionService implements SubscriptionServiceContract
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
    ) {}

    /**
     * Get the current active plan for a tenant.
     */
    public function getCurrentPlan(Tenant $tenant): Plan
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (! $subscription) {
            $freePlan = $this->plans->getFreePlan();

            if (! $freePlan) {
                throw new RuntimeException('Free plan not found in database.');
            }

            return $freePlan;
        }

        return $subscription->plan;
    }

    /**
     * Check if tenant has access to a specific feature.
     */
    public function hasFeature(Tenant $tenant, string $feature): bool
    {
        $plan = $this->getCurrentPlan($tenant);
        $features = $plan->features;

        if (! isset($features[$feature])) {
            return false;
        }

        $value = $features[$feature];

        // Boolean feature
        if (is_bool($value)) {
            return $value;
        }

        // String feature (e.g., 'basic', 'advanced') - any non-'none' string value means enabled
        return $value !== 'none';
    }

    /**
     * Get feature value (for tiered features like 'basic' vs 'advanced').
     */
    public function getFeatureValue(Tenant $tenant, string $feature): mixed
    {
        $plan = $this->getCurrentPlan($tenant);

        return $plan->features[$feature] ?? null;
    }

    /**
     * Get usage limit for a specific resource.
     */
    public function getLimit(Tenant $tenant, string $resource): int
    {
        $plan = $this->getCurrentPlan($tenant);

        return $plan->limits[$resource] ?? 0;
    }

    /**
     * Check if limit is unlimited (-1).
     */
    public function isUnlimited(Tenant $tenant, string $resource): bool
    {
        return $this->getLimit($tenant, $resource) === -1;
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(Tenant $tenant): bool
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (! $subscription) {
            return false;
        }

        return $subscription->stripe_status === 'trialing'
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->isFuture();
    }

    /**
     * Get trial days remaining.
     */
    public function getTrialDaysRemaining(Tenant $tenant): int
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (! $subscription) {
            return 0;
        }

        if (! $subscription->trial_ends_at) {
            return 0;
        }

        if ($subscription->trial_ends_at->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($subscription->trial_ends_at, false);
    }

    /**
     * Check if tenant can upgrade to a specific plan.
     */
    public function canUpgradeTo(Tenant $tenant, Plan $newPlan): bool
    {
        $currentPlan = $this->getCurrentPlan($tenant);

        return $newPlan->sort_order > $currentPlan->sort_order;
    }

    /**
     * Check if tenant can downgrade to a specific plan.
     */
    public function canDowngradeTo(Tenant $tenant, Plan $newPlan): bool
    {
        $currentPlan = $this->getCurrentPlan($tenant);

        return $newPlan->sort_order < $currentPlan->sort_order;
    }

    /**
     * Get plans available for upgrade.
     *
     * @return array<int, Plan>
     */
    public function getUpgradeOptions(Tenant $tenant): array
    {
        $currentPlan = $this->getCurrentPlan($tenant);

        return $this->plans->getPublic()
            ->filter(static fn (Plan $plan): bool => $plan->sort_order > $currentPlan->sort_order)
            ->values()
            ->all();
    }

    /**
     * Get plans available for downgrade.
     *
     * @return array<int, Plan>
     */
    public function getDowngradeOptions(Tenant $tenant): array
    {
        $currentPlan = $this->getCurrentPlan($tenant);

        return $this->plans->getPublic()
            ->filter(static fn (Plan $plan): bool => $plan->sort_order < $currentPlan->sort_order)
            ->values()
            ->all();
    }

    /**
     * Check if a scheduled downgrade or cancellation is pending.
     */
    public function hasPendingChange(Tenant $tenant): bool
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (! $subscription) {
            return false;
        }

        return $subscription->scheduled_plan_id !== null
            || $subscription->ends_at !== null;
    }

    /**
     * Get pending change details.
     *
     * @return array{type: string, plan: ?Plan, date: ?Carbon}|null
     */
    public function getPendingChange(Tenant $tenant): ?array
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (! $subscription) {
            return null;
        }

        if ($subscription->ends_at) {
            return [
                'type' => 'cancellation',
                'plan' => $this->plans->getFreePlan(),
                'date' => $subscription->ends_at,
            ];
        }

        if ($subscription->scheduled_plan_id) {
            return [
                'type' => 'downgrade',
                'plan' => $this->plans->findById($subscription->scheduled_plan_id),
                'date' => $subscription->scheduled_change_at,
            ];
        }

        return null;
    }
}
