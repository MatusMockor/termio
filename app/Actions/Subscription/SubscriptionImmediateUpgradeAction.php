<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionUpgradeBillingServiceContract;
use App\DTOs\Subscription\ImmediateUpgradeSubscriptionDTO;
use App\DTOs\Subscription\ValidationContext;
use App\Enums\BillingCycle;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionUpgradedNotification;
use App\Services\Validation\ValidationChainBuilder;

final class SubscriptionImmediateUpgradeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly ValidationChainBuilder $validationChainBuilder,
        private readonly SubscriptionUpgradeBillingServiceContract $upgradeBillingService,
    ) {}

    public function handle(ImmediateUpgradeSubscriptionDTO $dto): Subscription
    {
        $subscription = $this->subscriptions->findById($dto->subscriptionId);
        $newPlan = $this->plans->findById($dto->newPlanId);

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

        $updatedSubscription = $this->performImmediateUpgrade($subscription, $newPlan, $dto);

        $this->sendUpgradeNotification($tenant, $oldPlan, $newPlan);

        return $updatedSubscription;
    }

    private function performImmediateUpgrade(
        Subscription $subscription,
        Plan $newPlan,
        ImmediateUpgradeSubscriptionDTO $dto,
    ): Subscription {
        $billingCycle = $dto->billingCycle ?? $subscription->billing_cycle;
        $priceId = $this->upgradeBillingService->resolvePriceId($newPlan, BillingCycle::from($billingCycle));
        $updateData = $this->buildBaseUpgradeData($newPlan, $billingCycle, $priceId);

        if ($this->upgradeBillingService->isFreeSubscription($subscription)) {
            $stripeSubscription = $this->upgradeBillingService->createPaidSubscriptionFromFree(
                $subscription,
                $priceId,
            );

            return $this->persistSubscriptionUpdate($subscription, [
                ...$updateData,
                'stripe_id' => $stripeSubscription->id,
                'stripe_status' => $stripeSubscription->status,
            ]);
        }

        $this->upgradeBillingService->resumeCanceledPaidSubscription($subscription);

        if ($subscription->onTrial()) {
            $this->upgradeBillingService->swapPaidSubscription($subscription, $priceId);

            return $this->persistSubscriptionUpdate($subscription, $updateData);
        }

        $this->upgradeBillingService->swapPaidSubscriptionAndInvoice($subscription, $priceId);

        return $this->persistSubscriptionUpdate($subscription, $updateData);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function buildBaseUpgradeData(Plan $newPlan, string $billingCycle, string $priceId): array
    {
        return [
            'plan_id' => $newPlan->id,
            'stripe_price' => $priceId,
            'billing_cycle' => $billingCycle,
            'ends_at' => null,
            'scheduled_plan_id' => null,
            'scheduled_change_at' => null,
        ];
    }

    /**
     * @param  array<string, int|string|null>  $data
     */
    private function persistSubscriptionUpdate(Subscription $subscription, array $data): Subscription
    {
        return $this->subscriptions->transaction(
            fn (): Subscription => $this->subscriptions->update($subscription, $data),
        );
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
