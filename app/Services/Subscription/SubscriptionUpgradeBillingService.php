<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Contracts\Services\SubscriptionUpgradeBillingServiceContract;
use App\Enums\BillingCycle;
use App\Enums\SubscriptionType;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use Throwable;

final class SubscriptionUpgradeBillingService implements SubscriptionUpgradeBillingServiceContract
{
    public function __construct(
        private readonly DefaultPaymentMethodGuardContract $paymentMethodGuard,
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

    /**
     * @return object{id: string, status: string}
     */
    public function createPaidSubscriptionFromFree(
        Subscription $subscription,
        string $priceId,
    ): object {
        $tenant = $subscription->tenant;

        try {
            $defaultPaymentMethodId = $this->paymentMethodGuard->ensureLiveDefaultPaymentMethod($tenant);

            return $tenant->stripe()->subscriptions->create([
                'customer' => (string) $tenant->stripe_id,
                'items' => [
                    ['price' => $priceId],
                ],
                'default_payment_method' => $defaultPaymentMethodId,
            ], [
                'idempotency_key' => $this->buildIdempotencyKey('create_from_free', $subscription, $priceId),
            ]);
        } catch (SubscriptionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    /**
     * @return object{id: string, status: string, trial_end: int|null}
     */
    public function createTrialSubscriptionFromFree(
        Subscription $subscription,
        string $priceId,
        int $trialDays,
    ): object {
        $tenant = $subscription->tenant;

        try {
            $defaultPaymentMethodId = $this->paymentMethodGuard->ensureLiveDefaultPaymentMethod($tenant);

            return $tenant->stripe()->subscriptions->create([
                'customer' => (string) $tenant->stripe_id,
                'items' => [
                    ['price' => $priceId],
                ],
                'default_payment_method' => $defaultPaymentMethodId,
                'trial_period_days' => $trialDays,
            ], [
                'idempotency_key' => $this->buildIdempotencyKey('create_trial_from_free', $subscription, $priceId),
            ]);
        } catch (SubscriptionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
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
}
