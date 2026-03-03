<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\StripeService;
use App\Enums\BillingCycle;
use App\Enums\SubscriptionType;
use App\Models\Plan;
use App\Models\Tenant;
use App\Notifications\TrialStartedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class HandleCheckoutSessionCompletedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $tenantId,
        private readonly int $planId,
        private readonly BillingCycle $billingCycle,
        private readonly string $stripeSubscriptionId,
    ) {}

    public function handle(
        SubscriptionRepository $subscriptions,
        PlanRepository $plans,
        StripeService $stripeService,
    ): void {
        if ($subscriptions->findByStripeId($this->stripeSubscriptionId)) {
            Log::info('HandleCheckoutSessionCompletedJob: subscription already exists, skipping', [
                'stripe_subscription_id' => $this->stripeSubscriptionId,
            ]);

            return;
        }

        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return;
        }

        $plan = $this->resolvePlan($plans);

        if (! $plan) {
            return;
        }

        $this->removeExistingSubscription($tenant, $subscriptions, $stripeService);

        $stripeSubscription = $stripeService->getSubscription($this->stripeSubscriptionId);
        $subscriptionCreated = $this->createSubscriptionFromStripe($subscriptions, $tenant, $plan, $stripeSubscription);

        if (! $subscriptionCreated) {
            return;
        }

        $this->updateTenantPaymentMethod($tenant, $stripeService);
        $trialEndsAt = $this->resolveTrialEndsAt($stripeSubscription);
        if ($trialEndsAt !== null && $trialEndsAt->isFuture()) {
            $this->sendTrialNotification($tenant, $plan);
        }

        Log::info('HandleCheckoutSessionCompletedJob: subscription created', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => $this->stripeSubscriptionId,
        ]);
    }

    private function resolveTenant(): ?Tenant
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant) {
            return $tenant;
        }

        Log::warning('HandleCheckoutSessionCompletedJob: tenant not found', [
            'tenant_id' => $this->tenantId,
        ]);

        return null;
    }

    private function resolvePlan(PlanRepository $plans): ?Plan
    {
        $plan = $plans->findById($this->planId);

        if ($plan) {
            return $plan;
        }

        Log::warning('HandleCheckoutSessionCompletedJob: plan not found', [
            'plan_id' => $this->planId,
        ]);

        return null;
    }

    /**
     * @param  object  $stripeSubscription
     */
    private function createSubscriptionFromStripe(
        SubscriptionRepository $subscriptions,
        Tenant $tenant,
        Plan $plan,
        mixed $stripeSubscription,
    ): bool {
        $stripePriceId = $this->extractPriceId($stripeSubscription);
        $trialEndsAt = $this->resolveTrialEndsAt($stripeSubscription);

        try {
            $subscriptions->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'type' => SubscriptionType::Default->value,
                'stripe_id' => $this->stripeSubscriptionId,
                'stripe_status' => $stripeSubscription->status,
                'stripe_price' => $stripePriceId,
                'billing_cycle' => $this->billingCycle->value,
                'trial_ends_at' => $trialEndsAt,
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateStripeSubscriptionInsert($exception)) {
                Log::info('HandleCheckoutSessionCompletedJob: duplicate Stripe subscription insert ignored', [
                    'stripe_subscription_id' => $this->stripeSubscriptionId,
                ]);

                return false;
            }

            throw $exception;
        }

        return true;
    }

    /**
     * @param  object  $stripeSubscription
     */
    private function extractPriceId(mixed $stripeSubscription): ?string
    {
        $items = $stripeSubscription->items->data ?? [];

        if (! $items) {
            return null;
        }

        return $items[0]->price->id ?? null;
    }

    /**
     * @param  object  $stripeSubscription
     */
    private function resolveTrialEndsAt(mixed $stripeSubscription): ?\Illuminate\Support\Carbon
    {
        $trialEnd = $stripeSubscription->trial_end ?? null;

        if (! is_int($trialEnd) && ! is_float($trialEnd) && ! is_string($trialEnd)) {
            return null;
        }

        return now()->createFromTimestamp($trialEnd);
    }

    private function updateTenantPaymentMethod(Tenant $tenant, StripeService $stripeService): void
    {
        try {
            $customer = $stripeService->getCustomer((string) $tenant->stripe_id);
            $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method ?? null;

            if (! $defaultPaymentMethodId) {
                return;
            }

            $paymentMethod = $stripeService->getPaymentMethod((string) $defaultPaymentMethodId);

            $tenant->update([
                'pm_type' => $paymentMethod->type,
                'pm_last_four' => $paymentMethod->card->last4 ?? null,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('HandleCheckoutSessionCompletedJob: failed to update payment method', [
                'tenant_id' => $tenant->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function removeExistingSubscription(Tenant $tenant, SubscriptionRepository $subscriptions, StripeService $stripeService): void
    {
        $existing = $subscriptions->findActiveByTenant($tenant);

        if (! $existing) {
            return;
        }

        if ($existing->stripe_id === $this->stripeSubscriptionId) {
            return;
        }

        $freePrefix = config('subscription.free_subscription_prefix');
        $isFree = is_string($freePrefix)
            && $freePrefix !== ''
            && str_starts_with($existing->stripe_id, $freePrefix);

        if (! $isFree) {
            $stripeService->cancelSubscription($existing->stripe_id);
        }

        $existing->delete();
    }

    private function sendTrialNotification(Tenant $tenant, Plan $plan): void
    {
        $owner = $tenant->owner;

        if (! $owner) {
            return;
        }

        $owner->notify(new TrialStartedNotification($tenant, $plan));
    }

    private function isDuplicateStripeSubscriptionInsert(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return $sqlState === '23505' || $sqlState === '23000';
    }
}
