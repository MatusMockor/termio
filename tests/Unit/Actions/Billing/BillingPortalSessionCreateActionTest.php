<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Billing;

use App\Actions\Billing\BillingPortalSessionCreateAction;
use App\Contracts\Services\StripeService;
use App\Exceptions\BillingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Customer;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\InvalidRequestException;
use Tests\TestCase;

final class BillingPortalSessionCreateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_creates_stripe_customer_when_tenant_has_no_stripe_id(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => null]);

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->once())
            ->method('createCustomer')
            ->with($this->callback(fn (\App\Models\Tenant $tenant): bool => $tenant->id === $this->tenant->id))
            ->willReturn(Customer::constructFrom(['id' => 'cus_new_123']));
        $stripeService->expects($this->once())
            ->method('createBillingPortalSession')
            ->with('cus_new_123', 'https://app.termio.sk/billing')
            ->willReturn('https://billing.stripe.com/p/session/test_new');

        $action = new BillingPortalSessionCreateAction($stripeService);
        $result = $action->handle($this->tenant->fresh(), 'https://app.termio.sk/billing');

        $this->assertSame('https://billing.stripe.com/p/session/test_new', $result->url);
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'stripe_id' => 'cus_new_123',
        ]);
    }

    public function test_handle_uses_existing_stripe_customer_when_present(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => 'cus_existing_123']);

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->never())
            ->method('createCustomer');
        $stripeService->expects($this->once())
            ->method('createBillingPortalSession')
            ->with('cus_existing_123', 'https://app.termio.sk/billing')
            ->willReturn('https://billing.stripe.com/p/session/test_existing');

        $action = new BillingPortalSessionCreateAction($stripeService);
        $result = $action->handle($this->tenant->fresh(), 'https://app.termio.sk/billing');

        $this->assertSame('https://billing.stripe.com/p/session/test_existing', $result->url);
    }

    public function test_handle_maps_missing_customer_error_to_502(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => 'cus_deleted_123']);

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->never())
            ->method('createCustomer');
        $stripeService->expects($this->once())
            ->method('createBillingPortalSession')
            ->willThrowException(InvalidRequestException::factory(
                'No such customer: cus_deleted_123',
                404,
                null,
                ['error' => ['code' => 'resource_missing', 'param' => 'customer']],
                null,
                'resource_missing',
                'customer',
            ));

        $action = new BillingPortalSessionCreateAction($stripeService);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Unable to create billing portal session.');

        try {
            $action->handle($this->tenant->fresh(), 'https://app.termio.sk/billing');
        } catch (BillingException $exception) {
            $this->assertSame(502, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_handle_maps_stripe_connection_error_to_503(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => 'cus_existing_123']);

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->never())
            ->method('createCustomer');
        $stripeService->expects($this->once())
            ->method('createBillingPortalSession')
            ->willThrowException(ApiConnectionException::factory('Stripe API is unavailable', 503));

        $action = new BillingPortalSessionCreateAction($stripeService);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Billing service temporarily unavailable.');

        try {
            $action->handle($this->tenant->fresh(), 'https://app.termio.sk/billing');
        } catch (BillingException $exception) {
            $this->assertSame(503, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_handle_maps_customer_creation_failure_to_503(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => null]);

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->once())
            ->method('createCustomer')
            ->willThrowException(ApiConnectionException::factory('Stripe API is unavailable', 503));
        $stripeService->expects($this->never())
            ->method('createBillingPortalSession');

        $action = new BillingPortalSessionCreateAction($stripeService);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Billing service temporarily unavailable.');

        try {
            $action->handle($this->tenant->fresh(), 'https://app.termio.sk/billing');
        } catch (BillingException $exception) {
            $this->assertSame(503, $exception->getStatusCode());

            throw $exception;
        }
    }
}
