<?php

declare(strict_types=1);

namespace App\Services\Subscription\Strategies;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Contracts\Services\StripeBillingGatewayContract;
use App\Contracts\Services\StripeCustomerProvisionerContract;
use App\Contracts\Subscription\SubscriptionCreationStrategy;
use App\DTOs\Billing\CreateStripeSubscriptionDTO;
use App\DTOs\Billing\StripeSubscriptionResultDTO;
use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Enums\PlanSlug;
use App\Enums\SubscriptionType;
use App\Exceptions\BillingProviderException;
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
        private readonly StripeCustomerProvisionerContract $stripeCustomerProvisioner,
        private readonly StripeBillingGatewayContract $billingGateway,
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

        try {
            $subscription = DB::transaction(function () use ($dto, $tenant, $plan, $trialDays): Subscription {
                $customerId = $this->stripeCustomerProvisioner->ensureCustomerId($tenant);
                $defaultPaymentMethodId = $this->paymentMethodGuard->ensureLiveDefaultPaymentMethod($tenant);

                $priceId = $this->getPriceId($dto, $plan);

                $stripeSubscription = $this->createStripeSubscription(
                    $dto,
                    $customerId,
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
        } catch (BillingProviderException $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }

        $this->sendTrialNotification($dto, $tenant, $plan);

        return $subscription;
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

    private function createStripeSubscription(
        CreateSubscriptionDTO $dto,
        string $customerId,
        string $priceId,
        int $trialDays,
        string $defaultPaymentMethodId,
    ): StripeSubscriptionResultDTO {
        try {
            return $this->billingGateway->createSubscription(new CreateStripeSubscriptionDTO(
                customerId: $customerId,
                priceId: $priceId,
                defaultPaymentMethodId: $defaultPaymentMethodId,
                trialPeriodDays: $dto->startTrial ? $trialDays : null,
            ));
        } catch (BillingProviderException $exception) {
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
