<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Models\Tenant;

final class GetDashboardSubscriptionContextAction
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    /**
     * @return array{
     *     current_plan: \App\Models\Plan,
     *     subscription: \App\Models\Subscription|null,
     *     is_on_trial: bool,
     *     trial_days_remaining: int,
     *     pending_change: array{type: string, plan: \App\Models\Plan|null, date: \Carbon\Carbon|null}|null,
     *     is_free_subscription: bool,
     *     upgrade_options: array<int, \App\Models\Plan>,
     *     recommended_plan_id: int|null,
     *     actions: array{
     *         next_action: string,
     *         create_endpoint: string,
     *         upgrade_endpoint: string,
     *         default_billing_cycle: string,
     *         requires_default_payment_method: bool,
     *         has_default_payment_method: bool
     *     }
     * }
     */
    public function handle(Tenant $tenant): array
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);
        $currentPlan = $this->subscriptionService->getCurrentPlan($tenant);
        $upgradeOptions = $this->subscriptionService->getUpgradeOptions($tenant);
        $isFreeSubscription = $subscription !== null && str_starts_with($subscription->stripe_id, 'free_');
        $nextAction = $this->resolveNextAction($subscription !== null, $upgradeOptions !== []);

        return [
            'current_plan' => $currentPlan,
            'subscription' => $subscription,
            'is_on_trial' => $this->subscriptionService->isOnTrial($tenant),
            'trial_days_remaining' => $this->subscriptionService->getTrialDaysRemaining($tenant),
            'pending_change' => $this->subscriptionService->getPendingChange($tenant),
            'is_free_subscription' => $isFreeSubscription,
            'upgrade_options' => $upgradeOptions,
            'recommended_plan_id' => $upgradeOptions[0]->id ?? null,
            'actions' => [
                'next_action' => $nextAction,
                'create_endpoint' => '/api/subscriptions',
                'upgrade_endpoint' => '/api/subscriptions/upgrade',
                'default_billing_cycle' => $subscription !== null ? $subscription->billing_cycle : 'monthly',
                'requires_default_payment_method' => $isFreeSubscription,
                'has_default_payment_method' => $tenant->hasDefaultPaymentMethod(),
            ],
        ];
    }

    private function resolveNextAction(bool $hasSubscription, bool $hasUpgradeOptions): string
    {
        if (! $hasSubscription) {
            return 'create';
        }

        if (! $hasUpgradeOptions) {
            return 'none';
        }

        return 'upgrade';
    }
}
