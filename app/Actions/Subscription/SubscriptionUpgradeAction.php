<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionUpgradeBillingServiceContract;
use App\DTOs\Subscription\UpgradeSubscriptionDTO;
use App\DTOs\Subscription\ValidationContext;
use App\Enums\BillingCycle;
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
        private readonly SubscriptionUpgradeBillingServiceContract $upgradeBillingService,
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
            $priceId = $this->upgradeBillingService->resolvePriceId($newPlan, BillingCycle::from($billingCycle));

            // Cancel any scheduled downgrade
            if ($subscription->scheduled_plan_id) {
                $subscription = $this->subscriptions->update($subscription, [
                    'scheduled_plan_id' => null,
                    'scheduled_change_at' => null,
                ]);
            }

            if ($this->upgradeBillingService->isFreeSubscription($subscription)) {
                return $this->upgradeFromFreeSubscription($subscription, $newPlan, $billingCycle, $priceId);
            }

            $this->upgradeBillingService->swapPaidSubscription($subscription, $priceId);

            // Update local record
            return $this->subscriptions->update($subscription, [
                'plan_id' => $newPlan->id,
                'stripe_price' => $priceId,
                'billing_cycle' => $billingCycle,
            ]);
        });
    }

    private function upgradeFromFreeSubscription(
        Subscription $subscription,
        Plan $newPlan,
        string $billingCycle,
        string $priceId,
    ): Subscription {
        $stripeSubscription = $this->upgradeBillingService->createPaidSubscriptionFromFree(
            $subscription,
            $priceId,
        );

        return $this->subscriptions->update($subscription, [
            'plan_id' => $newPlan->id,
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $priceId,
            'billing_cycle' => $billingCycle,
            'ends_at' => null,
        ]);
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
