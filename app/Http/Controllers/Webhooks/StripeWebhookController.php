<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\BillingService;
use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Contracts\Services\StripeService as StripeServiceContract;
use App\Enums\BillingCycle;
use App\Jobs\Subscription\HandleCheckoutSessionCompletedJob;
use App\Jobs\Subscription\HandlePaymentFailedJob;
use App\Jobs\Subscription\HandleSubscriptionCanceledJob;
use App\Jobs\Subscription\HandleSubscriptionUpdatedJob;
use App\Models\Tenant;
use App\Notifications\TrialEndingNotification;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

final class StripeWebhookController extends CashierWebhookController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly InvoiceRepository $invoices,
        private readonly BillingService $billingService,
        private readonly StripeServiceContract $stripeService,
        private readonly DefaultPaymentMethodGuardContract $paymentMethodGuard,
    ) {}

    /**
     * Handle invoice.paid event - generate our invoice.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleInvoicePaid(array $payload): Response
    {
        /** @var array<string, mixed> $stripeInvoice */
        $stripeInvoice = $payload['data']['object'];
        /** @var string $stripeCustomerId */
        $stripeCustomerId = $stripeInvoice['customer'];
        /** @var string $stripeInvoiceId */
        $stripeInvoiceId = $stripeInvoice['id'];

        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if ($tenant === null) {
            Log::warning('Stripe webhook: tenant not found for customer', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return $this->successMethod();
        }

        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if ($subscription === null) {
            Log::warning('Stripe webhook: subscription not found for tenant', [
                'tenant_id' => $tenant->id,
            ]);

            return $this->successMethod();
        }

        // Check for idempotency - don't create duplicate invoices
        $existingInvoice = $this->invoices->findByStripeId($stripeInvoiceId);

        if ($existingInvoice !== null) {
            Log::info('Stripe webhook: invoice already exists, skipping', [
                'stripe_invoice_id' => $stripeInvoiceId,
            ]);

            return $this->successMethod();
        }

        // Retrieve full Stripe invoice object
        $stripeInvoiceObject = $this->stripeService->getInvoice($stripeInvoiceId);

        // Create our invoice
        $this->billingService->createInvoiceFromStripe($tenant, $subscription, $stripeInvoiceObject);

        Log::info('Stripe webhook: invoice created', [
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => $stripeInvoiceId,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle invoice.payment_failed event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        /** @var array<string, mixed> $stripeInvoice */
        $stripeInvoice = $payload['data']['object'];
        /** @var string $stripeCustomerId */
        $stripeCustomerId = $stripeInvoice['customer'];
        /** @var int $attemptCount */
        $attemptCount = $stripeInvoice['attempt_count'] ?? 1;

        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if ($tenant === null) {
            Log::warning('Stripe webhook: tenant not found for payment failure', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return $this->successMethod();
        }

        // Dispatch job to handle payment failure asynchronously
        HandlePaymentFailedJob::dispatch($tenant->id, $attemptCount);

        Log::warning('Stripe webhook: payment failed', [
            'tenant_id' => $tenant->id,
            'attempt_count' => $attemptCount,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.updated event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        /** @var array<string, mixed> $stripeSubscription */
        $stripeSubscription = $payload['data']['object'];
        /** @var string $stripeSubscriptionId */
        $stripeSubscriptionId = $stripeSubscription['id'];
        /** @var string $status */
        $status = $stripeSubscription['status'];

        $subscription = $this->subscriptions->findByStripeId($stripeSubscriptionId);

        if ($subscription === null) {
            Log::warning('Stripe webhook: subscription not found', [
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);

            return $this->successMethod();
        }

        // Dispatch job to handle subscription update asynchronously
        HandleSubscriptionUpdatedJob::dispatch(
            $subscription->id,
            $status,
            $stripeSubscription
        );

        Log::info('Stripe webhook: subscription update dispatched', [
            'subscription_id' => $subscription->id,
            'status' => $status,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.deleted event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        /** @var array<string, mixed> $stripeSubscription */
        $stripeSubscription = $payload['data']['object'];
        /** @var string $stripeSubscriptionId */
        $stripeSubscriptionId = $stripeSubscription['id'];

        $subscription = $this->subscriptions->findByStripeId($stripeSubscriptionId);

        if ($subscription === null) {
            Log::warning('Stripe webhook: subscription not found for deletion', [
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);

            return $this->successMethod();
        }

        // Dispatch job to handle cancellation asynchronously
        HandleSubscriptionCanceledJob::dispatch($subscription->id);

        Log::info('Stripe webhook: subscription canceled', [
            'subscription_id' => $subscription->id,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.trial_will_end event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionTrialWillEnd(array $payload): Response
    {
        /** @var array<string, mixed> $stripeSubscription */
        $stripeSubscription = $payload['data']['object'];
        /** @var string $stripeCustomerId */
        $stripeCustomerId = $stripeSubscription['customer'];

        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if ($tenant === null) {
            Log::warning('Stripe webhook: tenant not found for trial ending', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return $this->successMethod();
        }

        // Send trial ending notification to owner
        $owner = $tenant->owner;

        if ($owner !== null) {
            $owner->notify(new TrialEndingNotification($tenant));
        }

        Log::info('Stripe webhook: trial ending notification sent', [
            'tenant_id' => $tenant->id,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle payment_method.attached event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handlePaymentMethodAttached(array $payload): Response
    {
        /** @var array<string, mixed> $paymentMethod */
        $paymentMethod = $payload['data']['object'];
        /** @var string $stripeCustomerId */
        $stripeCustomerId = $paymentMethod['customer'];
        /** @var string $paymentMethodType */
        $paymentMethodType = $paymentMethod['type'];

        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if ($tenant === null) {
            Log::warning('Stripe webhook: tenant not found for payment method', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return $this->successMethod();
        }

        // Update tenant's payment method info
        /** @var array<string, mixed>|null $cardData */
        $cardData = $paymentMethod['card'] ?? null;
        /** @var string|null $lastFour */
        $lastFour = $cardData['last4'] ?? null;

        $tenant->update([
            'pm_type' => $paymentMethodType,
            'pm_last_four' => $lastFour,
        ]);

        Log::info('Stripe webhook: payment method attached', [
            'tenant_id' => $tenant->id,
            'pm_type' => $paymentMethodType,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle payment_method.detached event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handlePaymentMethodDetached(array $payload): Response
    {
        /** @var array<string, mixed> $paymentMethod */
        $paymentMethod = $payload['data']['object'];
        /** @var string|null $stripeCustomerId */
        $stripeCustomerId = $paymentMethod['customer'] ?? null;

        if (! is_string($stripeCustomerId) || $stripeCustomerId === '') {
            return $this->successMethod();
        }

        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if ($tenant === null) {
            Log::warning('Stripe webhook: tenant not found for detached payment method', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return $this->successMethod();
        }

        $paymentMethodState = $this->paymentMethodGuard->determineLiveDefaultPaymentMethod($tenant);

        if ($paymentMethodState === true) {
            return $this->successMethod();
        }

        if ($paymentMethodState === null) {
            Log::warning('Stripe webhook: payment method detached verification inconclusive', [
                'tenant_id' => $tenant->id,
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return $this->successMethod();
        }

        $tenant->update([
            'pm_type' => null,
            'pm_last_four' => null,
        ]);

        Log::info('Stripe webhook: cleared tenant payment method snapshot after detach', [
            'tenant_id' => $tenant->id,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle charge.refunded event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeRefunded(array $payload): Response
    {
        /** @var array<string, mixed> $charge */
        $charge = $payload['data']['object'];
        /** @var string|null $invoiceId */
        $invoiceId = $charge['invoice'] ?? null;

        if ($invoiceId === null) {
            return $this->successMethod();
        }

        $invoice = $this->invoices->findByStripeId($invoiceId);

        if ($invoice === null) {
            Log::warning('Stripe webhook: invoice not found for refund', [
                'stripe_invoice_id' => $invoiceId,
            ]);

            return $this->successMethod();
        }

        $existingNotes = $invoice->notes ?? '';
        $refundNote = 'Refunded on '.now()->format('Y-m-d');
        $newNotes = $existingNotes !== '' ? $existingNotes."\n".$refundNote : $refundNote;

        $this->invoices->update($invoice, [
            'status' => 'void',
            'notes' => $newNotes,
        ]);

        Log::info('Stripe webhook: invoice refunded', [
            'invoice_id' => $invoice->id,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle checkout.session.completed event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        /** @var array<string, mixed> $session */
        $session = $payload['data']['object'];

        if (! $this->isSubscriptionCheckoutSession($session)) {
            return $this->successMethod();
        }

        /** @var string $stripeSubscriptionId */
        $stripeSubscriptionId = $session['subscription'] ?? '';
        /** @var string $stripeCustomerId */
        $stripeCustomerId = $session['customer'] ?? '';

        if ($this->hasMissingCheckoutIdentifiers($stripeSubscriptionId, $stripeCustomerId, $session['id'] ?? null)) {
            return $this->successMethod();
        }

        if ($this->subscriptionAlreadyProcessed($stripeSubscriptionId)) {
            return $this->successMethod();
        }

        $tenant = $this->resolveCheckoutTenant($stripeCustomerId);

        if ($tenant === null) {
            return $this->successMethod();
        }

        $metadata = $this->resolveCheckoutMetadata($session);
        $this->logCheckoutTenantMetadataMismatch($metadata, $tenant->id, $stripeSubscriptionId);

        $planId = $this->resolveCheckoutPlanId($metadata);
        if ($planId === null) {
            Log::warning('Stripe webhook: checkout session missing valid plan metadata', [
                'tenant_id' => $tenant->id,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'metadata' => $metadata,
            ]);

            return $this->successMethod();
        }

        $billingCycle = $this->resolveCheckoutBillingCycle($metadata);

        HandleCheckoutSessionCompletedJob::dispatch(
            $tenant->id,
            $planId,
            $billingCycle,
            $stripeSubscriptionId,
        );

        Log::info('Stripe webhook: checkout session completed, job dispatched', [
            'tenant_id' => $tenant->id,
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function isSubscriptionCheckoutSession(array $session): bool
    {
        /** @var string $mode */
        $mode = $session['mode'] ?? '';

        return $mode === 'subscription';
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, string>
     */
    private function resolveCheckoutMetadata(array $session): array
    {
        /** @var array<string, mixed> $subscriptionData */
        $subscriptionData = $session['subscription_data'] ?? [];
        /** @var array<string, string> $metadata */
        $metadata = $subscriptionData['metadata'] ?? $session['metadata'] ?? [];

        return $metadata;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function resolveCheckoutBillingCycle(array $metadata): BillingCycle
    {
        return BillingCycle::tryFrom((string) ($metadata['billing_cycle'] ?? '')) ?? BillingCycle::Monthly;
    }

    private function hasMissingCheckoutIdentifiers(
        string $stripeSubscriptionId,
        string $stripeCustomerId,
        mixed $sessionId,
    ): bool {
        if ($stripeSubscriptionId !== '' && $stripeCustomerId !== '') {
            return false;
        }

        Log::warning('Stripe webhook: checkout session missing subscription or customer', [
            'session_id' => is_string($sessionId) && $sessionId !== '' ? $sessionId : 'unknown',
        ]);

        return true;
    }

    private function subscriptionAlreadyProcessed(string $stripeSubscriptionId): bool
    {
        if ($this->subscriptions->findByStripeId($stripeSubscriptionId) === null) {
            return false;
        }

        Log::info('Stripe webhook: subscription already exists for checkout session, skipping', [
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);

        return true;
    }

    private function resolveCheckoutTenant(string $stripeCustomerId): ?Tenant
    {
        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if ($tenant !== null) {
            return $tenant;
        }

        Log::warning('Stripe webhook: tenant not found for checkout session', [
            'stripe_customer_id' => $stripeCustomerId,
        ]);

        return null;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function resolveCheckoutPlanId(array $metadata): ?int
    {
        $planId = filter_var($metadata['plan_id'] ?? null, FILTER_VALIDATE_INT);

        if (! is_int($planId) || $planId < 1) {
            return null;
        }

        return $planId;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function logCheckoutTenantMetadataMismatch(
        array $metadata,
        int $resolvedTenantId,
        string $stripeSubscriptionId,
    ): void {
        $tenantIdFromMetadata = filter_var($metadata['tenant_id'] ?? null, FILTER_VALIDATE_INT);

        if (! is_int($tenantIdFromMetadata) || $tenantIdFromMetadata === $resolvedTenantId) {
            return;
        }

        Log::warning('Stripe webhook: checkout session tenant metadata mismatch', [
            'resolved_tenant_id' => $resolvedTenantId,
            'metadata_tenant_id' => $tenantIdFromMetadata,
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);
    }
}
