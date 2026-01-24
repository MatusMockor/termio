<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\StripeService;
use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\TrialStartedNotification;
use Illuminate\Support\Facades\DB;

final class SubscriptionCreateAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly StripeService $stripe,
    ) {}

    public function handle(CreateSubscriptionDTO $dto, Tenant $tenant): Subscription
    {
        // Check if tenant already has an active subscription
        $existingSubscription = $this->subscriptions->findActiveByTenant($tenant);

        if ($existingSubscription) {
            throw SubscriptionException::alreadySubscribed();
        }

        $plan = $this->plans->findById($dto->planId);

        if (! $plan) {
            throw SubscriptionException::planNotFound($dto->planId);
        }

        // FREE plan - no Stripe subscription needed
        if ($plan->slug === 'free') {
            return $this->createFreeSubscription($tenant, $plan);
        }

        return $this->createPaidSubscription($dto, $tenant, $plan);
    }

    private function createFreeSubscription(Tenant $tenant, Plan $plan): Subscription
    {
        return $this->subscriptions->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'type' => 'default',
            'stripe_id' => 'free_'.$tenant->id,
            'stripe_status' => 'active',
            'stripe_price' => null,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => null,
        ]);
    }

    private function createPaidSubscription(
        CreateSubscriptionDTO $dto,
        Tenant $tenant,
        Plan $plan
    ): Subscription {
        $subscription = DB::transaction(function () use ($dto, $tenant, $plan): Subscription {
            // Ensure tenant has Stripe customer ID
            if (! $tenant->stripe_id) {
                $customer = $this->stripe->createCustomer($tenant);
                $tenant->update(['stripe_id' => $customer->id]);
            }

            // Get the appropriate Stripe price ID
            $priceId = $dto->billingCycle === 'yearly'
                ? $plan->stripe_yearly_price_id
                : $plan->stripe_monthly_price_id;

            if (! $priceId) {
                throw SubscriptionException::stripeError('No Stripe price ID configured for this plan.');
            }

            // Create Stripe subscription using Laravel Cashier
            $stripeSubscriptionBuilder = $tenant->newSubscription('default', $priceId);

            if ($dto->startTrial) {
                $stripeSubscriptionBuilder->trialDays(config('subscription.trial_days'));
            }

            $stripeSubscription = $stripeSubscriptionBuilder->create($dto->paymentMethodId);

            // Create local subscription record
            return $this->subscriptions->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'type' => 'default',
                'stripe_id' => $stripeSubscription->stripe_id,
                'stripe_status' => $stripeSubscription->stripe_status,
                'stripe_price' => $priceId,
                'billing_cycle' => $dto->billingCycle,
                'trial_ends_at' => $dto->startTrial ? now()->addDays(config('subscription.trial_days')) : null,
            ]);
        });

        // Send trial started notification if trial was started
        if ($dto->startTrial) {
            $owner = $tenant->owner;

            if ($owner) {
                $owner->notify(new TrialStartedNotification($tenant, $plan));
            }
        }

        return $subscription;
    }
}
