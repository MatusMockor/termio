<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Contracts\Services\StripeBillingGatewayContract;
use App\Contracts\Services\SubscriptionUpgradeBillingServiceContract;
use App\DTOs\Billing\CreateStripeSubscriptionDTO;
use App\DTOs\Billing\StripeSubscriptionResultDTO;
use App\Enums\BillingCycle;
use App\Enums\SubscriptionType;
use App\Exceptions\BillingProviderException;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use Throwable;

final class SubscriptionUpgradeBillingService implements SubscriptionUpgradeBillingServiceContract
{
    public function __construct(
        private readonly DefaultPaymentMethodGuardContract $paymentMethodGuard,
        private readonly StripeBillingGatewayContract $billingGateway,
    ) {}

    public function resolvePriceId(Plan $plan, BillingCycle $billingCycle): string
    {
        $priceId = $billingCycle === BillingCycle::Yearly
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (! $priceId) {
            throw SubscriptionException::stripeError('No Stripe price ID configured for this plan.');
        }

        return $priceId;
    }

    public function isFreeSubscription(Subscription $subscription): bool
    {
        return str_starts_with($subscription->stripe_id, 'free_');
    }

    public function createPaidSubscriptionFromFree(
        Subscription $subscription,
        string $priceId,
    ): StripeSubscriptionResultDTO {
        $tenant = $subscription->tenant;

        try {
            $defaultPaymentMethodId = $this->paymentMethodGuard->ensureLiveDefaultPaymentMethod($tenant);
            $customerId = $this->resolveCustomerId($tenant);

            return $this->billingGateway->createSubscription(new CreateStripeSubscriptionDTO(
                customerId: $customerId,
                priceId: $priceId,
                defaultPaymentMethodId: $defaultPaymentMethodId,
                idempotencyKey: $this->buildIdempotencyKey('create_from_free', $subscription, $priceId),
            ));
        } catch (SubscriptionException $exception) {
            throw $exception;
        } catch (BillingProviderException $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    public function createTrialSubscriptionFromFree(
        Subscription $subscription,
        string $priceId,
        int $trialDays,
    ): StripeSubscriptionResultDTO {
        $tenant = $subscription->tenant;

        try {
            $defaultPaymentMethodId = $this->paymentMethodGuard->ensureLiveDefaultPaymentMethod($tenant);
            $customerId = $this->resolveCustomerId($tenant);

            return $this->billingGateway->createSubscription(new CreateStripeSubscriptionDTO(
                customerId: $customerId,
                priceId: $priceId,
                defaultPaymentMethodId: $defaultPaymentMethodId,
                trialPeriodDays: $trialDays,
                idempotencyKey: $this->buildIdempotencyKey('create_trial_from_free', $subscription, $priceId),
            ));
        } catch (SubscriptionException $exception) {
            throw $exception;
        } catch (BillingProviderException $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    public function swapPaidSubscription(Subscription $subscription, string $priceId): void
    {
        $this->paymentMethodGuard->ensureLiveDefaultPaymentMethod($subscription->tenant);
        $stripeSubscription = $this->getCashierSubscription($subscription);

        if ($this->isAlreadyOnTargetPrice($stripeSubscription, $priceId)) {
            return;
        }

        try {
            $stripeSubscription->swap($priceId);
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    public function swapPaidSubscriptionAndInvoice(Subscription $subscription, string $priceId): void
    {
        $this->paymentMethodGuard->ensureLiveDefaultPaymentMethod($subscription->tenant);
        $stripeSubscription = $this->getCashierSubscription($subscription);

        if ($this->isAlreadyOnTargetPrice($stripeSubscription, $priceId)) {
            return;
        }

        try {
            $stripeSubscription->swapAndInvoice($priceId);
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    public function resumeCanceledPaidSubscription(Subscription $subscription): void
    {
        if (! $subscription->ends_at) {
            return;
        }

        $stripeSubscription = $this->getCashierSubscription($subscription);

        if (! $stripeSubscription->onGracePeriod()) {
            return;
        }

        try {
            $stripeSubscription->resume();
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    private function getCashierSubscription(Subscription $subscription): \Laravel\Cashier\Subscription
    {
        $stripeSubscription = $subscription->tenant->subscription(SubscriptionType::Default->value);

        if (! $stripeSubscription) {
            throw SubscriptionException::noActiveSubscription();
        }

        return $stripeSubscription;
    }

    private function isAlreadyOnTargetPrice(
        \Laravel\Cashier\Subscription $stripeSubscription,
        string $priceId,
    ): bool {
        $currentPriceId = $stripeSubscription->asStripeSubscription()
            ->items
            ->data[0]
            ->price
            ->id
            ?? null;

        return $currentPriceId === $priceId;
    }

    private function buildIdempotencyKey(string $operation, Subscription $subscription, string $priceId): string
    {
        return hash('sha256', implode('|', [
            $operation,
            (string) $subscription->id,
            (string) $subscription->tenant_id,
            $priceId,
        ]));
    }

    private function resolveCustomerId(\App\Models\Tenant $tenant): string
    {
        if (! $tenant->hasStripeId()) {
            throw SubscriptionException::paymentMethodRequired();
        }

        return (string) $tenant->stripe_id;
    }
}
