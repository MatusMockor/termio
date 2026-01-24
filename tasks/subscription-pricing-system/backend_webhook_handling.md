# Stripe Webhook Handling

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Backend
**Complexity**: Medium
**Dependencies**: `backend_stripe_integration.md`, `backend_subscription_service.md`, `backend_billing_invoicing.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement Stripe webhook handling for payment events, subscription changes, and invoice updates. Uses Laravel Cashier webhook controller with custom event handlers per PRD requirements for payment success, failure, and subscription lifecycle events.

**Architecture Impact**: Adds webhook controller and event handlers. Integrates with notification system for payment events. Updates subscription and invoice records based on Stripe events.

**Risk Assessment**:
- **High**: Webhook signature verification must be correct
- **Medium**: Idempotency - must handle duplicate events
- **Medium**: Event ordering - must handle out-of-order events
- **Low**: Queue processing for async event handling

## Component Architecture

### Webhook Controller

**File**: `app/Http/Controllers/Webhooks/StripeWebhookController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Jobs\Subscription\HandlePaymentFailedJob;
use App\Jobs\Subscription\HandleSubscriptionCanceledJob;
use App\Jobs\Subscription\HandleSubscriptionUpdatedJob;
use App\Models\Tenant;
use App\Services\Billing\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Stripe\Event;
use Symfony\Component\HttpFoundation\Response;

final class StripeWebhookController extends CashierWebhookController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly InvoiceRepository $invoices,
        private readonly BillingService $billingService,
    ) {}

    /**
     * Handle invoice.paid event - generate our invoice.
     */
    protected function handleInvoicePaid(array $payload): Response
    {
        $stripeInvoice = $payload['data']['object'];
        $stripeCustomerId = $stripeInvoice['customer'];

        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if (!$tenant) {
            Log::warning('Stripe webhook: tenant not found for customer', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return $this->successMethod();
        }

        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (!$subscription) {
            Log::warning('Stripe webhook: subscription not found for tenant', [
                'tenant_id' => $tenant->id,
            ]);

            return $this->successMethod();
        }

        // Retrieve full Stripe invoice object
        $stripeInvoiceObject = \Stripe\Invoice::retrieve($stripeInvoice['id']);

        // Create our invoice
        $this->billingService->createInvoiceFromStripe($tenant, $subscription, $stripeInvoiceObject);

        Log::info('Stripe webhook: invoice created', [
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => $stripeInvoice['id'],
        ]);

        return $this->successMethod();
    }

    /**
     * Handle invoice.payment_failed event.
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $stripeInvoice = $payload['data']['object'];
        $stripeCustomerId = $stripeInvoice['customer'];
        $attemptCount = $stripeInvoice['attempt_count'] ?? 1;

        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if (!$tenant) {
            return $this->successMethod();
        }

        // Dispatch job to handle payment failure
        HandlePaymentFailedJob::dispatch($tenant->id, $attemptCount);

        Log::warning('Stripe webhook: payment failed', [
            'tenant_id' => $tenant->id,
            'attempt_count' => $attemptCount,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.updated event.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $stripeSubscription = $payload['data']['object'];

        $subscription = $this->subscriptions->findByStripeId($stripeSubscription['id']);

        if (!$subscription) {
            Log::warning('Stripe webhook: subscription not found', [
                'stripe_subscription_id' => $stripeSubscription['id'],
            ]);

            return $this->successMethod();
        }

        // Dispatch job to handle subscription update
        HandleSubscriptionUpdatedJob::dispatch(
            $subscription->id,
            $stripeSubscription['status'],
            $stripeSubscription
        );

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.deleted event.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $stripeSubscription = $payload['data']['object'];

        $subscription = $this->subscriptions->findByStripeId($stripeSubscription['id']);

        if (!$subscription) {
            return $this->successMethod();
        }

        // Dispatch job to handle cancellation
        HandleSubscriptionCanceledJob::dispatch($subscription->id);

        Log::info('Stripe webhook: subscription canceled', [
            'subscription_id' => $subscription->id,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.trial_will_end event.
     */
    protected function handleCustomerSubscriptionTrialWillEnd(array $payload): Response
    {
        $stripeSubscription = $payload['data']['object'];
        $stripeCustomerId = $stripeSubscription['customer'];

        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if (!$tenant) {
            return $this->successMethod();
        }

        // Send trial ending notification
        $owner = $tenant->owner;
        if ($owner) {
            $owner->notify(new \App\Notifications\TrialEndingNotification($tenant));
        }

        Log::info('Stripe webhook: trial ending soon', [
            'tenant_id' => $tenant->id,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle payment_method.attached event.
     */
    protected function handlePaymentMethodAttached(array $payload): Response
    {
        $paymentMethod = $payload['data']['object'];
        $stripeCustomerId = $paymentMethod['customer'];

        $tenant = Tenant::where('stripe_id', $stripeCustomerId)->first();

        if (!$tenant) {
            return $this->successMethod();
        }

        // Update tenant's payment method info
        $tenant->update([
            'pm_type' => $paymentMethod['type'],
            'pm_last_four' => $paymentMethod['card']['last4'] ?? null,
        ]);

        Log::info('Stripe webhook: payment method attached', [
            'tenant_id' => $tenant->id,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle charge.refunded event.
     */
    protected function handleChargeRefunded(array $payload): Response
    {
        $charge = $payload['data']['object'];
        $invoiceId = $charge['invoice'] ?? null;

        if (!$invoiceId) {
            return $this->successMethod();
        }

        $invoice = $this->invoices->findByStripeId($invoiceId);

        if ($invoice) {
            $invoice->update([
                'status' => 'void',
                'notes' => ($invoice->notes ? $invoice->notes . "\n" : '') . 'Refunded on ' . now()->format('Y-m-d'),
            ]);

            Log::info('Stripe webhook: invoice refunded', [
                'invoice_id' => $invoice->id,
            ]);
        }

        return $this->successMethod();
    }
}
```

### Payment Failed Job

**File**: `app/Jobs/Subscription/HandlePaymentFailedJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Models\Tenant;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionDowngradedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class HandlePaymentFailedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly int $tenantId,
        private readonly int $attemptCount,
    ) {}

    public function handle(
        SubscriptionRepository $subscriptions,
        PlanRepository $plans
    ): void {
        $tenant = Tenant::find($this->tenantId);

        if (!$tenant) {
            return;
        }

        $owner = $tenant->owner;

        if (!$owner) {
            return;
        }

        // Send payment failed notification
        $owner->notify(new PaymentFailedNotification($tenant, $this->attemptCount));

        // If max attempts reached, downgrade to FREE
        if ($this->attemptCount >= self::MAX_ATTEMPTS) {
            $subscription = $subscriptions->findActiveByTenant($tenant);

            if ($subscription) {
                $freePlan = $plans->getFreePlan();

                // Update to free plan
                $subscriptions->update($subscription, [
                    'plan_id' => $freePlan->id,
                    'stripe_status' => 'canceled',
                ]);

                $owner->notify(new SubscriptionDowngradedNotification($tenant, $freePlan));

                Log::warning('Subscription downgraded due to payment failure', [
                    'tenant_id' => $tenant->id,
                    'attempts' => $this->attemptCount,
                ]);
            }
        }
    }
}
```

### Subscription Updated Job

**File**: `app/Jobs/Subscription/HandleSubscriptionUpdatedJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class HandleSubscriptionUpdatedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $subscriptionId,
        private readonly string $status,
        private readonly array $stripeData,
    ) {}

    public function handle(SubscriptionRepository $subscriptions): void
    {
        $subscription = $subscriptions->findById($this->subscriptionId);

        if (!$subscription) {
            return;
        }

        $updateData = [
            'stripe_status' => $this->status,
        ];

        // Update trial end date if present
        if (isset($this->stripeData['trial_end'])) {
            $updateData['trial_ends_at'] = Carbon::createFromTimestamp($this->stripeData['trial_end']);
        }

        // Update cancel date if present
        if (isset($this->stripeData['cancel_at'])) {
            $updateData['ends_at'] = Carbon::createFromTimestamp($this->stripeData['cancel_at']);
        } elseif (isset($this->stripeData['canceled_at']) && $this->stripeData['cancel_at_period_end']) {
            $updateData['ends_at'] = Carbon::createFromTimestamp($this->stripeData['current_period_end']);
        }

        // Update current price if changed
        if (isset($this->stripeData['items']['data'][0]['price']['id'])) {
            $updateData['stripe_price'] = $this->stripeData['items']['data'][0]['price']['id'];
        }

        $subscriptions->update($subscription, $updateData);
    }
}
```

### Subscription Canceled Job

**File**: `app/Jobs/Subscription/HandleSubscriptionCanceledJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Notifications\SubscriptionEndedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class HandleSubscriptionCanceledJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $subscriptionId,
    ) {}

    public function handle(
        SubscriptionRepository $subscriptions,
        PlanRepository $plans
    ): void {
        $subscription = $subscriptions->findById($this->subscriptionId);

        if (!$subscription) {
            return;
        }

        $tenant = $subscription->tenant;
        $freePlan = $plans->getFreePlan();

        // Update subscription to free plan
        $subscriptions->update($subscription, [
            'plan_id' => $freePlan->id,
            'stripe_status' => 'canceled',
            'ends_at' => now(),
            'scheduled_plan_id' => null,
            'scheduled_change_at' => null,
        ]);

        // Notify owner
        $owner = $tenant->owner;
        if ($owner) {
            $owner->notify(new SubscriptionEndedNotification($tenant));
        }

        Log::info('Subscription ended, downgraded to FREE', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
        ]);
    }
}
```

### Webhook Route

**File**: `routes/api.php` - Add webhook route

```php
use App\Http\Controllers\Webhooks\StripeWebhookController;

// Stripe webhooks - no auth middleware
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook')
    ->withoutMiddleware(['auth:sanctum', 'throttle:api']);
```

### CSRF Exception

**File**: `bootstrap/app.php` - Exclude webhook from CSRF verification

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'api/webhooks/stripe',
    ]);
})
```

## Webhook Events Handled

| Event | Handler | Action |
|-------|---------|--------|
| `invoice.paid` | `handleInvoicePaid` | Create invoice record, generate PDF |
| `invoice.payment_failed` | `handleInvoicePaymentFailed` | Notify user, downgrade after 3 failures |
| `customer.subscription.updated` | `handleCustomerSubscriptionUpdated` | Sync subscription status |
| `customer.subscription.deleted` | `handleCustomerSubscriptionDeleted` | Downgrade to FREE, notify |
| `customer.subscription.trial_will_end` | `handleCustomerSubscriptionTrialWillEnd` | Send trial ending notification |
| `payment_method.attached` | `handlePaymentMethodAttached` | Update tenant payment info |
| `charge.refunded` | `handleChargeRefunded` | Mark invoice as void |

## Testing Strategy

### E2E Test
- `TestStripeWebhooks` covering invoice.paid, payment_failed, subscription.deleted
- Verify: Invoice created, notifications sent, subscription status updated

### Manual Verification
- Use Stripe CLI to forward webhooks locally
- Trigger test events and verify handling
- Check database records updated correctly

## Stripe CLI Testing

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login to Stripe
stripe login

# Forward webhooks to local
stripe listen --forward-to http://localhost:8000/api/webhooks/stripe

# Trigger test events
stripe trigger invoice.paid
stripe trigger invoice.payment_failed
stripe trigger customer.subscription.deleted
```

## Implementation Steps

1. **Medium** - Create StripeWebhookController extending Cashier controller
2. **Medium** - Implement handleInvoicePaid with invoice creation
3. **Medium** - Implement handleInvoicePaymentFailed with retry logic
4. **Medium** - Implement handleCustomerSubscriptionUpdated
5. **Small** - Implement handleCustomerSubscriptionDeleted
6. **Small** - Implement handleCustomerSubscriptionTrialWillEnd
7. **Small** - Implement handlePaymentMethodAttached
8. **Small** - Implement handleChargeRefunded
9. **Medium** - Create HandlePaymentFailedJob
10. **Medium** - Create HandleSubscriptionUpdatedJob
11. **Small** - Create HandleSubscriptionCanceledJob
12. **Small** - Add webhook route without auth middleware
13. **Small** - Exclude webhook from CSRF verification
14. **Medium** - Write feature tests with mocked Stripe events
15. **Small** - Run Pint and verify code style

## Cross-Task Dependencies

- **Depends on**: `backend_stripe_integration.md`, `backend_subscription_service.md`, `backend_billing_invoicing.md`
- **Blocks**: `backend_email_notifications.md` (needs webhook events for triggers)
- **Parallel work**: None - depends on billing being complete
