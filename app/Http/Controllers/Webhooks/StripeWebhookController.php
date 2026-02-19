<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\BillingService;
use App\Contracts\Services\StripeService as StripeServiceContract;
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
}
