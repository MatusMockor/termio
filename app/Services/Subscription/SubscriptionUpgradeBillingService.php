<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Services\SubscriptionUpgradeBillingServiceContract;
use App\Enums\SubscriptionType;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Throwable;

final class SubscriptionUpgradeBillingService implements SubscriptionUpgradeBillingServiceContract
{
    public function resolvePriceId(Plan $plan, string $billingCycle): string
    {
        $priceId = $billingCycle === 'yearly'
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

        if (! $tenant->hasDefaultPaymentMethod()) {
            throw SubscriptionException::paymentMethodRequired();
        }

        try {
            $tenant->createOrGetStripeCustomer();
            $defaultPaymentMethodId = $this->getDefaultPaymentMethodIdFromStripe($tenant);

            return $tenant->stripe()->subscriptions->create([
                'customer' => (string) $tenant->stripe_id,
                'items' => [
                    ['price' => $priceId],
                ],
                'default_payment_method' => $defaultPaymentMethodId,
            ]);
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    public function swapPaidSubscription(Subscription $subscription, string $priceId): void
    {
        $stripeSubscription = $this->getCashierSubscription($subscription);

        try {
            $stripeSubscription->swap($priceId);
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    public function swapPaidSubscriptionAndInvoice(Subscription $subscription, string $priceId): void
    {
        $stripeSubscription = $this->getCashierSubscription($subscription);

        try {
            $stripeSubscription->swapAndInvoice($priceId);
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }
    }

    public function resumeCanceledPaidSubscription(Subscription $subscription): void
    {
        if ($subscription->ends_at === null) {
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

    private function getCashierSubscription(Subscription $subscription): \Laravel\Cashier\Subscription
    {
        $stripeSubscription = $subscription->tenant->subscription(SubscriptionType::Default->value);

        if ($stripeSubscription === null) {
            throw SubscriptionException::noActiveSubscription();
        }

        return $stripeSubscription;
    }
}
