<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Billing;

use App\Actions\Billing\BillingPortalSessionCreateAction;
use App\Contracts\Services\StripeBillingGatewayContract;
use App\Contracts\Services\StripeCustomerProvisionerContract;
use App\Exceptions\BillingException;
use App\Exceptions\BillingProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BillingPortalSessionCreateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_creates_portal_session_with_provisioned_customer(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => null]);

        $provisioner = $this->createMock(StripeCustomerProvisionerContract::class);
        $provisioner->expects($this->once())
            ->method('ensureCustomerId')
            ->with($this->callback(fn (\App\Models\Tenant $tenant): bool => $tenant->id === $this->tenant->id))
            ->willReturn('cus_new_123');

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->once())
            ->method('createPortalSession')
            ->with('cus_new_123', 'https://app.termio.sk/billing')
            ->willReturn('https://billing.stripe.com/p/session/test_new');

        $action = new BillingPortalSessionCreateAction($provisioner, $gateway);
        $result = $action->handle($this->tenant->fresh(), 'https://app.termio.sk/billing');

        $this->assertSame('https://billing.stripe.com/p/session/test_new', $result->url);
    }

    public function test_handle_maps_missing_customer_error_to_502(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => 'cus_deleted_123']);

        $provisioner = $this->createMock(StripeCustomerProvisionerContract::class);
        $provisioner->expects($this->once())
            ->method('ensureCustomerId')
            ->willReturn('cus_deleted_123');

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->once())
            ->method('createPortalSession')
            ->willThrowException(new BillingProviderException(
                'No such customer: cus_deleted_123',
                404,
                'resource_missing',
                'customer',
            ));

        $action = new BillingPortalSessionCreateAction($provisioner, $gateway);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Unable to create billing portal session.');

        try {
            $action->handle($this->tenant->fresh(), 'https://app.termio.sk/billing');
        } catch (BillingException $exception) {
            $this->assertSame(502, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_handle_maps_gateway_error_to_503(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => 'cus_existing_123']);

        $provisioner = $this->createMock(StripeCustomerProvisionerContract::class);
        $provisioner->expects($this->once())
            ->method('ensureCustomerId')
            ->willReturn('cus_existing_123');

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->once())
            ->method('createPortalSession')
            ->willThrowException(new BillingProviderException('Stripe API is unavailable', 503));

        $action = new BillingPortalSessionCreateAction($provisioner, $gateway);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Billing service temporarily unavailable.');

        try {
            $action->handle($this->tenant->fresh(), 'https://app.termio.sk/billing');
        } catch (BillingException $exception) {
            $this->assertSame(503, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_handle_maps_customer_provisioning_failure_to_503(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => null]);

        $provisioner = $this->createMock(StripeCustomerProvisionerContract::class);
        $provisioner->expects($this->once())
            ->method('ensureCustomerId')
            ->willThrowException(new BillingProviderException('Stripe API is unavailable', 503));

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->never())
            ->method('createPortalSession');

        $action = new BillingPortalSessionCreateAction($provisioner, $gateway);

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
