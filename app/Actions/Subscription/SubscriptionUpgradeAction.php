<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\DTOs\Subscription\UpgradeSubscriptionDTO;
use App\DTOs\Subscription\ValidationContext;
use App\Enums\SubscriptionType;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionUpgradedNotification;
use App\Services\Validation\ValidationChainBuilder;
use Illuminate\Support\Facades\DB;
use Throwable;

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
            $priceId = $this->resolvePriceId($newPlan, $billingCycle);

            // Cancel any scheduled downgrade
            if ($subscription->scheduled_plan_id) {
                $subscription = $this->subscriptions->update($subscription, [
                    'scheduled_plan_id' => null,
                    'scheduled_change_at' => null,
                ]);
            }

            if ($this->isFreeSubscription($subscription)) {
                return $this->upgradeFromFreeSubscription($subscription, $newPlan, $billingCycle, $priceId);
            }

            $this->swapPaidSubscription($subscription, $priceId);

            // Update local record
            return $this->subscriptions->update($subscription, [
                'plan_id' => $newPlan->id,
                'stripe_price' => $priceId,
                'billing_cycle' => $billingCycle,
            ]);
        });
    }

    private function resolvePriceId(Plan $newPlan, string $billingCycle): string
    {
        $priceId = $billingCycle === 'yearly'
            ? $newPlan->stripe_yearly_price_id
            : $newPlan->stripe_monthly_price_id;

        if (! $priceId) {
            throw SubscriptionException::stripeError('No Stripe price ID configured for this plan.');
        }

        return $priceId;
    }

    private function isFreeSubscription(Subscription $subscription): bool
    {
        return str_starts_with($subscription->stripe_id, 'free_');
    }

    private function swapPaidSubscription(Subscription $subscription, string $priceId): void
    {
        $stripeSubscription = $subscription->tenant->subscription(SubscriptionType::Default->value);

        if ($stripeSubscription === null) {
            throw SubscriptionException::noActiveSubscription();
        }

        try {
            $stripeSubscription->swap($priceId);
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    private function upgradeFromFreeSubscription(
        Subscription $subscription,
        Plan $newPlan,
        string $billingCycle,
        string $priceId,
    ): Subscription {
        $tenant = $subscription->tenant;

        if (! $tenant->hasDefaultPaymentMethod()) {
            throw SubscriptionException::paymentMethodRequired();
        }

        try {
            $tenant->createOrGetStripeCustomer();
            $defaultPaymentMethodId = $this->getDefaultPaymentMethodIdFromStripe($tenant);

            $stripeSubscription = $tenant->stripe()->subscriptions->create([
                'customer' => (string) $tenant->stripe_id,
                'items' => [
                    ['price' => $priceId],
                ],
                'default_payment_method' => $defaultPaymentMethodId,
            ]);
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }

        return $this->subscriptions->update($subscription, [
            'plan_id' => $newPlan->id,
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $priceId,
            'billing_cycle' => $billingCycle,
            'ends_at' => null,
        ]);
    }

    private function getDefaultPaymentMethodIdFromStripe(Tenant $tenant): string
    {
        $stripeCustomer = $tenant->asStripeCustomer();
        $defaultPaymentMethod = $stripeCustomer->invoice_settings?->default_payment_method;
        $defaultPaymentMethodId = is_string($defaultPaymentMethod)
            ? $defaultPaymentMethod
            : ($defaultPaymentMethod->id ?? null);

        if (! $defaultPaymentMethodId) {
            throw SubscriptionException::paymentMethodRequired();
        }

        return $defaultPaymentMethodId;
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
