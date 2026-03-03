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
        $paymentMethodId = $this->ensurePaymentMethod($tenant);

        $payload = $this->buildSubscriptionPayload($tenant, $priceId, $paymentMethodId);
        $options = $this->buildStripeOptions('create_from_free', $subscription, $priceId);

        return $this->createStripeSubscription($tenant, $payload, $options);
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
        $paymentMethodId = $this->ensurePaymentMethod($tenant);

        $payload = $this->buildSubscriptionPayload($tenant, $priceId, $paymentMethodId, $trialDays);
        $options = $this->buildStripeOptions('create_trial_from_free', $subscription, $priceId);

        return $this->createStripeSubscription($tenant, $payload, $options);
    }

    public function swapPaidSubscription(Subscription $subscription, string $priceId): void
    {
        $this->ensurePaymentMethod($subscription->tenant);
        $stripeSubscription = $this->getCashierSubscription($subscription);

        if ($this->isAlreadyOnTargetPrice($stripeSubscription, $priceId)) {
            return;
        }

        $this->executeStripeOperation(static fn () => $stripeSubscription->swap($priceId));
    }

    public function swapPaidSubscriptionAndInvoice(Subscription $subscription, string $priceId): void
    {
        $this->ensurePaymentMethod($subscription->tenant);
        $stripeSubscription = $this->getCashierSubscription($subscription);

        if ($this->isAlreadyOnTargetPrice($stripeSubscription, $priceId)) {
            return;
        }

        $this->executeStripeOperation(static fn () => $stripeSubscription->swapAndInvoice($priceId));
    }

    public function resumeCanceledPaidSubscription(Subscription $subscription): void
    {
        if (! $this->isSubscriptionCanceled($subscription)) {
            return;
        }

        $stripeSubscription = $this->getCashierSubscription($subscription);

        if (! $this->isOnGracePeriod($stripeSubscription)) {
            return;
        }

        $this->executeStripeOperation(static fn () => $stripeSubscription->resume());
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

    private function ensurePaymentMethod(Tenant $tenant): string
    {
        return $this->paymentMethodGuard->ensureLiveDefaultPaymentMethod($tenant);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubscriptionPayload(
        Tenant $tenant,
        string $priceId,
        string $paymentMethodId,
        ?int $trialDays = null
    ): array {
        $payload = [
            'customer' => (string) $tenant->stripe_id,
            'items' => [
                ['price' => $priceId],
            ],
            'default_payment_method' => $paymentMethodId,
        ];

        if ($trialDays !== null) {
            $payload['trial_period_days'] = $trialDays;
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function buildStripeOptions(string $operation, Subscription $subscription, string $priceId): array
    {
        return [
            'idempotency_key' => $this->buildIdempotencyKey($operation, $subscription, $priceId),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $options
     * @return object{id: string, status: string, trial_end?: int|null}
     */
    private function createStripeSubscription(Tenant $tenant, array $payload, array $options): object
    {
        try {
            return $tenant->stripe()->subscriptions->create($payload, $options);
        } catch (SubscriptionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    private function executeStripeOperation(callable $operation): void
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    private function isSubscriptionCanceled(Subscription $subscription): bool
    {
        return $subscription->ends_at !== null;
    }

    private function isOnGracePeriod(\Laravel\Cashier\Subscription $stripeSubscription): bool
    {
        return $stripeSubscription->onGracePeriod();
    }
}
