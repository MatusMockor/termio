<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\StripeBillingGatewayContract;
use App\Contracts\Services\StripeCustomerProvisionerContract;
use App\DTOs\Billing\CheckoutSessionDTO;
use App\Enums\BillingCycle;
use App\Exceptions\BillingException;
use App\Exceptions\BillingProviderException;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

final class CheckoutSessionCreateAction
{
    public function __construct(
        private readonly StripeCustomerProvisionerContract $stripeCustomerProvisioner,
        private readonly StripeBillingGatewayContract $billingGateway,
        private readonly PlanRepository $plans,
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    public function handle(
        Tenant $tenant,
        int $planId,
        BillingCycle $billingCycle,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionDTO {
        $plan = $this->plans->findById($planId);

        if (! $plan) {
            throw SubscriptionException::planNotFound($planId);
        }

        $priceId = $this->resolvePriceId($plan, $billingCycle);
        $shouldStartTrial = $this->shouldStartTrial($tenant);
        $subscriptionData = $this->buildSubscriptionData($tenant, $plan, $billingCycle, $shouldStartTrial);
        /** @var array<string, string> $metadata */
        $metadata = $subscriptionData['metadata'];

        try {
            $customerId = $this->stripeCustomerProvisioner->ensureCustomerId($tenant);

            return $this->billingGateway->createCheckoutSession([
                'customer' => $customerId,
                'mode' => 'subscription',
                'line_items' => [['price' => $priceId, 'quantity' => 1]],
                'subscription_data' => $subscriptionData,
                'metadata' => $metadata,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'payment_method_collection' => 'always',
            ]);
        } catch (BillingProviderException $exception) {
            $this->logCheckoutError($tenant, $exception);

            throw BillingException::serviceUnavailable();
        }
    }

    private function resolvePriceId(Plan $plan, BillingCycle $billingCycle): string
    {
        $priceId = $billingCycle === BillingCycle::Yearly
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (! $priceId) {
            throw SubscriptionException::stripeError('No Stripe price ID configured for this plan.');
        }

        return $priceId;
    }

    private function shouldStartTrial(Tenant $tenant): bool
    {
        $activeSubscription = $this->subscriptions->findActiveByTenant($tenant);

        if (! $activeSubscription) {
            return true;
        }

        $freePrefix = (string) config('subscription.free_subscription_prefix');
        if ($freePrefix === '') {
            return false;
        }

        return str_starts_with($activeSubscription->stripe_id, $freePrefix);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubscriptionData(
        Tenant $tenant,
        Plan $plan,
        BillingCycle $billingCycle,
        bool $shouldStartTrial,
    ): array {
        $subscriptionData = [
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'plan_id' => (string) $plan->id,
                'billing_cycle' => $billingCycle->value,
            ],
        ];

        if ($shouldStartTrial) {
            $subscriptionData['trial_period_days'] = config('subscription.trial_days');
        }

        return $subscriptionData;
    }

    private function logCheckoutError(Tenant $tenant, BillingProviderException $exception): void
    {
        Log::error('Failed to create checkout session.', [
            'tenant_id' => $tenant->id,
            'stripe_id' => $tenant->stripe_id,
            'http_status' => $exception->getHttpStatus(),
            'stripe_code' => $exception->getStripeCode(),
            'error' => $exception->getMessage(),
        ]);
    }
}
