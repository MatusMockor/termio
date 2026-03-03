<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Jobs\Subscription\HandlePaymentFailedJob;
use App\Jobs\Subscription\HandleSubscriptionCanceledJob;
use App\Jobs\Subscription\HandleSubscriptionUpdatedJob;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\TrialEndingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\TestCase;

final class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Plan $freePlan;

    private Plan $smartPlan;

    private StripeWebhookController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTenantWithOwner();

        $this->freePlan = Plan::factory()->free()->create();
        $this->smartPlan = Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
            'monthly_price' => 14.90,
        ]);

        // Add Stripe ID to tenant
        $this->tenant->update([
            'stripe_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
        ]);

        $this->controller = app(StripeWebhookController::class);
    }

    public function test_invoice_paid_handles_unknown_customer(): void
    {
        $payload = $this->createWebhookPayload('invoice.paid', [
            'id' => 'in_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'customer' => 'cus_unknown',
            'subtotal' => 1490,
            'paid' => true,
        ]);

        $response = $this->invokeProtectedMethod('handleInvoicePaid', $payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_invoice_paid_handles_tenant_without_subscription(): void
    {
        $payload = $this->createWebhookPayload('invoice.paid', [
            'id' => 'in_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'customer' => $this->tenant->stripe_id,
            'subtotal' => 1490,
            'paid' => true,
        ]);

        $response = $this->invokeProtectedMethod('handleInvoicePaid', $payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_invoice_paid_does_not_duplicate_invoice(): void
    {
        $subscription = Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();
        $stripeInvoiceId = 'in_'.fake()->regexify('[A-Za-z0-9]{24}');

        // Create existing invoice
        Invoice::factory()->forTenant($this->tenant)->forSubscription($subscription)->create([
            'stripe_invoice_id' => $stripeInvoiceId,
        ]);

        $payload = $this->createWebhookPayload('invoice.paid', [
            'id' => $stripeInvoiceId,
            'customer' => $this->tenant->stripe_id,
            'subtotal' => 1490,
            'paid' => true,
        ]);

        $response = $this->invokeProtectedMethod('handleInvoicePaid', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify no duplicate was created
        $this->assertDatabaseCount(Invoice::class, 1);
    }

    public function test_payment_failed_dispatches_job(): void
    {
        Queue::fake();

        Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $payload = $this->createWebhookPayload('invoice.payment_failed', [
            'id' => 'in_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'customer' => $this->tenant->stripe_id,
            'attempt_count' => 1,
        ]);

        $response = $this->invokeProtectedMethod('handleInvoicePaymentFailed', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        Queue::assertPushed(HandlePaymentFailedJob::class);
    }

    public function test_payment_failed_handles_unknown_customer(): void
    {
        Queue::fake();

        $payload = $this->createWebhookPayload('invoice.payment_failed', [
            'id' => 'in_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'customer' => 'cus_unknown',
            'attempt_count' => 1,
        ]);

        $response = $this->invokeProtectedMethod('handleInvoicePaymentFailed', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        Queue::assertNotPushed(HandlePaymentFailedJob::class);
    }

    public function test_subscription_updated_dispatches_job(): void
    {
        Queue::fake();

        $subscription = Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $payload = $this->createWebhookPayload('customer.subscription.updated', [
            'id' => $subscription->stripe_id,
            'customer' => $this->tenant->stripe_id,
            'status' => 'active',
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'id' => 'price_new123',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->invokeProtectedMethod('handleCustomerSubscriptionUpdated', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        Queue::assertPushed(HandleSubscriptionUpdatedJob::class);
    }

    public function test_subscription_updated_handles_unknown_subscription(): void
    {
        Queue::fake();

        $payload = $this->createWebhookPayload('customer.subscription.updated', [
            'id' => 'sub_unknown',
            'customer' => $this->tenant->stripe_id,
            'status' => 'active',
        ]);

        $response = $this->invokeProtectedMethod('handleCustomerSubscriptionUpdated', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        Queue::assertNotPushed(HandleSubscriptionUpdatedJob::class);
    }

    public function test_subscription_deleted_dispatches_job(): void
    {
        Queue::fake();

        $subscription = Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $payload = $this->createWebhookPayload('customer.subscription.deleted', [
            'id' => $subscription->stripe_id,
            'customer' => $this->tenant->stripe_id,
        ]);

        $response = $this->invokeProtectedMethod('handleCustomerSubscriptionDeleted', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        Queue::assertPushed(HandleSubscriptionCanceledJob::class);
    }

    public function test_trial_will_end_sends_notification(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->smartPlan)
            ->onTrial()
            ->create();

        $payload = $this->createWebhookPayload('customer.subscription.trial_will_end', [
            'id' => $subscription->stripe_id,
            'customer' => $this->tenant->stripe_id,
            'trial_end' => now()->addDays(3)->timestamp,
        ]);

        $response = $this->invokeProtectedMethod('handleCustomerSubscriptionTrialWillEnd', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        Notification::assertSentTo($this->user, TrialEndingNotification::class);
    }

    public function test_trial_will_end_handles_unknown_customer(): void
    {
        Notification::fake();

        $payload = $this->createWebhookPayload('customer.subscription.trial_will_end', [
            'id' => 'sub_test',
            'customer' => 'cus_unknown',
            'trial_end' => now()->addDays(3)->timestamp,
        ]);

        $response = $this->invokeProtectedMethod('handleCustomerSubscriptionTrialWillEnd', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        Notification::assertNothingSent();
    }

    public function test_payment_method_attached_updates_tenant(): void
    {
        $payload = $this->createWebhookPayload('payment_method.attached', [
            'id' => 'pm_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'customer' => $this->tenant->stripe_id,
            'type' => 'card',
            'card' => [
                'brand' => 'visa',
                'last4' => '4242',
            ],
        ]);

        $response = $this->invokeProtectedMethod('handlePaymentMethodAttached', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        $this->tenant->refresh();
        $this->assertEquals('card', $this->tenant->pm_type);
        $this->assertEquals('4242', $this->tenant->pm_last_four);
    }

    public function test_payment_method_attached_handles_unknown_customer(): void
    {
        $payload = $this->createWebhookPayload('payment_method.attached', [
            'id' => 'pm_test',
            'customer' => 'cus_unknown',
            'type' => 'card',
        ]);

        $response = $this->invokeProtectedMethod('handlePaymentMethodAttached', $payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_payment_method_detached_clears_tenant_payment_snapshot_when_no_live_default_exists(): void
    {
        $this->tenant->update([
            'pm_type' => 'card',
            'pm_last_four' => '4242',
        ]);

        $guard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $guard->expects($this->once())
            ->method('determineLiveDefaultPaymentMethod')
            ->with($this->callback(static fn (Tenant $tenant): bool => $tenant->id > 0))
            ->willReturn(false);

        $this->app->instance(DefaultPaymentMethodGuardContract::class, $guard);
        $controller = app(StripeWebhookController::class);

        $payload = $this->createWebhookPayload('payment_method.detached', [
            'id' => 'pm_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'customer' => $this->tenant->stripe_id,
            'type' => 'card',
        ]);

        $response = $this->invokeProtectedMethod('handlePaymentMethodDetached', $payload, $controller);

        $this->assertEquals(200, $response->getStatusCode());

        $this->tenant->refresh();
        $this->assertNull($this->tenant->pm_type);
        $this->assertNull($this->tenant->pm_last_four);
    }

    public function test_payment_method_detached_keeps_snapshot_when_live_default_exists(): void
    {
        $this->tenant->update([
            'pm_type' => 'card',
            'pm_last_four' => '4242',
        ]);

        $guard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $guard->expects($this->once())
            ->method('determineLiveDefaultPaymentMethod')
            ->willReturn(true);

        $this->app->instance(DefaultPaymentMethodGuardContract::class, $guard);
        $controller = app(StripeWebhookController::class);

        $payload = $this->createWebhookPayload('payment_method.detached', [
            'id' => 'pm_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'customer' => $this->tenant->stripe_id,
            'type' => 'card',
        ]);

        $response = $this->invokeProtectedMethod('handlePaymentMethodDetached', $payload, $controller);

        $this->assertEquals(200, $response->getStatusCode());

        $this->tenant->refresh();
        $this->assertSame('card', $this->tenant->pm_type);
        $this->assertSame('4242', $this->tenant->pm_last_four);
    }

    public function test_payment_method_detached_keeps_snapshot_when_verification_is_inconclusive(): void
    {
        $this->tenant->update([
            'pm_type' => 'card',
            'pm_last_four' => '4242',
        ]);

        $guard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $guard->expects($this->once())
            ->method('determineLiveDefaultPaymentMethod')
            ->willReturn(null);

        $this->app->instance(DefaultPaymentMethodGuardContract::class, $guard);
        $controller = app(StripeWebhookController::class);

        $payload = $this->createWebhookPayload('payment_method.detached', [
            'id' => 'pm_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'customer' => $this->tenant->stripe_id,
            'type' => 'card',
        ]);

        $response = $this->invokeProtectedMethod('handlePaymentMethodDetached', $payload, $controller);

        $this->assertEquals(200, $response->getStatusCode());

        $this->tenant->refresh();
        $this->assertSame('card', $this->tenant->pm_type);
        $this->assertSame('4242', $this->tenant->pm_last_four);
    }

    public function test_charge_refunded_voids_invoice(): void
    {
        $subscription = Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();
        $stripeInvoiceId = 'in_'.fake()->regexify('[A-Za-z0-9]{24}');

        $invoice = Invoice::factory()->forTenant($this->tenant)->forSubscription($subscription)->paid()->create([
            'stripe_invoice_id' => $stripeInvoiceId,
        ]);

        $payload = $this->createWebhookPayload('charge.refunded', [
            'id' => 'ch_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'invoice' => $stripeInvoiceId,
            'amount_refunded' => 1490,
        ]);

        $response = $this->invokeProtectedMethod('handleChargeRefunded', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        $invoice->refresh();
        $this->assertEquals('void', $invoice->status);
        $this->assertStringContainsString('Refunded on', $invoice->notes ?? '');
    }

    public function test_charge_refunded_handles_missing_invoice(): void
    {
        $payload = $this->createWebhookPayload('charge.refunded', [
            'id' => 'ch_test',
            'invoice' => 'in_nonexistent',
        ]);

        $response = $this->invokeProtectedMethod('handleChargeRefunded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_charge_refunded_handles_missing_invoice_id(): void
    {
        $payload = $this->createWebhookPayload('charge.refunded', [
            'id' => 'ch_test',
        ]);

        $response = $this->invokeProtectedMethod('handleChargeRefunded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Create a Stripe webhook payload.
     *
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function createWebhookPayload(string $event, array $object): array
    {
        return [
            'id' => 'evt_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'type' => $event,
            'data' => [
                'object' => $object,
            ],
        ];
    }

    /**
     * Invoke a protected method on the controller.
     *
     * @param  array<string, mixed>  $payload
     */
    private function invokeProtectedMethod(
        string $method,
        array $payload,
        ?StripeWebhookController $controller = null,
    ): \Symfony\Component\HttpFoundation\Response {
        $targetController = $controller ?? $this->controller;
        $reflection = new ReflectionMethod($targetController, $method);

        return $reflection->invoke($targetController, $payload);
    }
}
