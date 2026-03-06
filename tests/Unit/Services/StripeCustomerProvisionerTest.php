<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Services\StripeService;
use App\Exceptions\BillingProviderException;
use App\Services\Billing\StripeCustomerProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Stripe\Customer;
use Tests\TestCase;

final class StripeCustomerProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_customer_id_returns_existing_stripe_id_without_creating_customer(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => 'cus_existing_123']);

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->never())
            ->method('createCustomer');

        $provisioner = new StripeCustomerProvisioner($stripeService);
        $customerId = $provisioner->ensureCustomerId($this->tenant->fresh());

        $this->assertSame('cus_existing_123', $customerId);
    }

    public function test_ensure_customer_id_creates_and_persists_customer_when_missing(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => null]);

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->once())
            ->method('createCustomer')
            ->with($this->callback(fn (\App\Models\Tenant $tenant): bool => $tenant->id === $this->tenant->id))
            ->willReturn(Customer::constructFrom(['id' => 'cus_new_123']));

        $provisioner = new StripeCustomerProvisioner($stripeService);
        $customerId = $provisioner->ensureCustomerId($this->tenant->fresh());

        $this->assertSame('cus_new_123', $customerId);
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'stripe_id' => 'cus_new_123',
        ]);
    }

    public function test_ensure_customer_id_wraps_customer_creation_failure(): void
    {
        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => null]);

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->once())
            ->method('createCustomer')
            ->willThrowException(new RuntimeException('stripe unavailable'));

        $provisioner = new StripeCustomerProvisioner($stripeService);

        $this->expectException(BillingProviderException::class);
        $this->expectExceptionMessage('stripe unavailable');

        $provisioner->ensureCustomerId($this->tenant->fresh());
    }
}
