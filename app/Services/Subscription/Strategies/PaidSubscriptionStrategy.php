<?php

declare(strict_types=1);

namespace App\Services\Subscription\Strategies;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Contracts\Services\StripeService;
use App\Contracts\Subscription\SubscriptionCreationStrategy;
use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Enums\PlanSlug;
use App\Enums\SubscriptionType;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\TrialStartedNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PaidSubscriptionStrategy implements SubscriptionCreationStrategy
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly StripeService $stripe,
        private readonly DefaultPaymentMethodGuardContract $paymentMethodGuard,
    ) {}

    public function supports(Plan $plan): bool
    {
        return $plan->slug !== PlanSlug::Free->value;
    }

    public function create(CreateSubscriptionDTO $dto, Tenant $tenant, Plan $plan): Subscription
    {
        /** @var int $trialDays */
        $trialDays = config('subscription.trial_days');

        $subscription = DB::transaction(function () use ($dto, $tenant, $plan, $trialDays): Subscription {
            $this->ensureStripeCustomer($tenant);
            $defaultPaymentMethodId = $this->paymentMethodGuard->ensureLiveDefaultPaymentMethod($tenant);

            $priceId = $this->getPriceId($dto, $plan);

            $stripeSubscription = $this->createStripeSubscription(
                $dto,
                $tenant,
                $priceId,
                $trialDays,
                $defaultPaymentMethodId,
            );

            return $this->subscriptions->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'type' => SubscriptionType::Default->value,
                'stripe_id' => $stripeSubscription->id,
                'stripe_status' => $stripeSubscription->status,
                'stripe_price' => $priceId,
                'billing_cycle' => $dto->billingCycle,
                'trial_ends_at' => $dto->startTrial ? now()->addDays($trialDays) : null,
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
     * @return object{id: string, status: string}
     */
    private function createStripeSubscription(
        CreateSubscriptionDTO $dto,
        Tenant $tenant,
        string $priceId,
        int $trialDays,
        string $defaultPaymentMethodId,
    ): object {
        $payload = [
            'customer' => (string) $tenant->stripe_id,
            'items' => [
                ['price' => $priceId],
            ],
            'default_payment_method' => $defaultPaymentMethodId,
        ];

        if ($dto->startTrial) {
            $payload['trial_period_days'] = $trialDays;
        }

        try {
            return $tenant->stripe()->subscriptions->create($payload);
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
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
