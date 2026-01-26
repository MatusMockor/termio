<?php

declare(strict_types=1);

namespace App\Services\Subscription\Strategies;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\StripeService;
use App\Contracts\Subscription\SubscriptionCreationStrategy;
use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Enums\SubscriptionType;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\TrialStartedNotification;
use Illuminate\Support\Facades\DB;

final class PaidSubscriptionStrategy implements SubscriptionCreationStrategy
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly StripeService $stripe,
    ) {}

    public function supports(Plan $plan): bool
    {
        return $plan->slug !== 'free';
    }

    public function create(CreateSubscriptionDTO $dto, Tenant $tenant, Plan $plan): Subscription
    {
        $subscription = DB::transaction(function () use ($dto, $tenant, $plan): Subscription {
            $this->ensureStripeCustomer($tenant);

            $priceId = $this->getPriceId($dto, $plan);

            $stripeSubscription = $this->createStripeSubscription($dto, $tenant, $priceId);

            return $this->subscriptions->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'type' => SubscriptionType::Default->value,
                'stripe_id' => $stripeSubscription->stripe_id,
                'stripe_status' => $stripeSubscription->stripe_status,
                'stripe_price' => $priceId,
                'billing_cycle' => $dto->billingCycle,
                'trial_ends_at' => $dto->startTrial ? now()->addDays((int) config('subscription.trial_days')) : null,
            ]);
        });

        $this->sendTrialNotification($dto, $tenant, $plan);

        return $subscription;
    }

    private function ensureStripeCustomer(Tenant $tenant): void
    {
        if ($tenant->stripe_id) {
            return;
        }

        $customer = $this->stripe->createCustomer($tenant);
        $tenant->update(['stripe_id' => $customer->id]);
    }

    private function getPriceId(CreateSubscriptionDTO $dto, Plan $plan): string
    {
        $priceId = $dto->billingCycle === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (! $priceId) {
            throw SubscriptionException::stripeError('No Stripe price ID configured for this plan.');
        }

        return $priceId;
    }

    /**
     * @return object{stripe_id: string, stripe_status: string}
     */
    private function createStripeSubscription(CreateSubscriptionDTO $dto, Tenant $tenant, string $priceId): object
    {
        $stripeSubscriptionBuilder = $tenant->newSubscription(SubscriptionType::Default->value, $priceId);

        if ($dto->startTrial) {
            $stripeSubscriptionBuilder->trialDays((int) config('subscription.trial_days'));
        }

        return $stripeSubscriptionBuilder->create($dto->paymentMethodId);
    }

    private function sendTrialNotification(CreateSubscriptionDTO $dto, Tenant $tenant, Plan $plan): void
    {
        if (! $dto->startTrial) {
            return;
        }

        $owner = $tenant->owner;

        if (! $owner) {
            return;
        }

        $owner->notify(new TrialStartedNotification($tenant, $plan));
    }
}
