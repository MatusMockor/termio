<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\DTOs\Subscription\UpgradeSubscriptionDTO;
use App\DTOs\Subscription\ValidationContext;
use App\Enums\SubscriptionType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionUpgradedNotification;
use App\Services\Validation\ValidationChainBuilder;
use Illuminate\Support\Facades\DB;

final class SubscriptionUpgradeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly ValidationChainBuilder $validationChainBuilder,
    ) {}

    public function handle(UpgradeSubscriptionDTO $dto): Subscription
    {
        $subscription = $this->subscriptions->findById($dto->subscriptionId);
        $newPlan = $this->plans->findById($dto->newPlanId);

        // Build and run validation chain
        $validationChain = $this->validationChainBuilder->buildUpgradeChain();
        $context = ValidationContext::forUpgrade(
            subscription: $subscription,
            newPlan: $newPlan,
            subscriptionId: $dto->subscriptionId,
            planId: $dto->newPlanId,
        );
        $validationChain->validate($context);

        /** @var Subscription $subscription */
        /** @var Plan $newPlan */
        $tenant = $subscription->tenant;
        $oldPlan = $subscription->plan;

        $updatedSubscription = $this->performUpgrade($subscription, $newPlan, $dto);

        // Send notification
        $this->sendUpgradeNotification($tenant, $oldPlan, $newPlan);

        return $updatedSubscription;
    }

    private function performUpgrade(
        Subscription $subscription,
        Plan $newPlan,
        UpgradeSubscriptionDTO $dto,
    ): Subscription {
        return DB::transaction(function () use ($subscription, $newPlan, $dto): Subscription {
            $billingCycle = $dto->billingCycle ?? $subscription->billing_cycle;

            $priceId = $billingCycle === 'yearly'
                ? $newPlan->stripe_yearly_price_id
                : $newPlan->stripe_monthly_price_id;

            // Cancel any scheduled downgrade
            if ($subscription->scheduled_plan_id) {
                $subscription = $this->subscriptions->update($subscription, [
                    'scheduled_plan_id' => null,
                    'scheduled_change_at' => null,
                ]);
            }

            // Only swap if not a free plan subscription
            if (! str_starts_with($subscription->stripe_id, 'free_')) {
                // Swap to new plan with proration
                $subscription->tenant->subscription(SubscriptionType::Default->value)->swap($priceId);
            }

            // Update local record
            return $this->subscriptions->update($subscription, [
                'plan_id' => $newPlan->id,
                'stripe_price' => $priceId,
                'billing_cycle' => $billingCycle,
            ]);
        });
    }

    private function sendUpgradeNotification(
        Tenant $tenant,
        Plan $oldPlan,
        Plan $newPlan,
    ): void {
        $owner = $tenant->owner;

        if (! $owner) {
            return;
        }

        $owner->notify(new SubscriptionUpgradedNotification($tenant, $oldPlan, $newPlan));
    }
}
