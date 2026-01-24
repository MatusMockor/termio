<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\DTOs\Subscription\UpgradeSubscriptionDTO;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionUpgradedNotification;
use Illuminate\Support\Facades\DB;

final class SubscriptionUpgradeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly SubscriptionServiceContract $subscriptionService,
    ) {}

    public function handle(UpgradeSubscriptionDTO $dto): Subscription
    {
        $subscription = $this->subscriptions->findById($dto->subscriptionId);

        if (! $subscription) {
            throw SubscriptionException::subscriptionNotFound($dto->subscriptionId);
        }

        $newPlan = $this->plans->findById($dto->newPlanId);

        if (! $newPlan) {
            throw SubscriptionException::planNotFound($dto->newPlanId);
        }

        $tenant = $subscription->tenant;
        $oldPlan = $subscription->plan;

        if (! $this->subscriptionService->canUpgradeTo($tenant, $newPlan)) {
            throw SubscriptionException::cannotUpgrade($oldPlan, $newPlan);
        }

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
                $subscription->tenant->subscription('default')->swap($priceId);
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
        \App\Models\Tenant $tenant,
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
