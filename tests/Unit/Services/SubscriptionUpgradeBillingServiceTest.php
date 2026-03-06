<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Contracts\Services\StripeBillingGatewayContract;
use App\DTOs\Billing\CreateStripeSubscriptionDTO;
use App\DTOs\Billing\StripeSubscriptionResultDTO;
use App\Exceptions\BillingProviderException;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscription\SubscriptionUpgradeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionUpgradeBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_trial_subscription_from_free_requires_live_default_payment_method(): void
    {
        $subscription = $this->createFreeSubscription();

        $paymentMethodGuard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $paymentMethodGuard->expects($this->once())
            ->method('ensureLiveDefaultPaymentMethod')
            ->with($this->callback(static fn (Tenant $tenant): bool => $tenant->id === $subscription->tenant_id))
            ->willThrowException(SubscriptionException::paymentMethodRequired());

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->never())
            ->method('createSubscription');

        $service = new SubscriptionUpgradeBillingService($paymentMethodGuard, $gateway);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Payment method is required for paid plans.');

        $service->createTrialSubscriptionFromFree($subscription, 'price_easy_monthly', 14);
    }

    public function test_create_trial_subscription_from_free_creates_trial_subscription_with_trial_period_days(): void
    {
        $subscription = $this->createFreeSubscription();

        $paymentMethodGuard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $paymentMethodGuard->expects($this->once())
            ->method('ensureLiveDefaultPaymentMethod')
            ->with($this->callback(static fn (Tenant $tenant): bool => $tenant->id === $subscription->tenant_id))
            ->willReturn('pm_default_123');

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->once())
            ->method('createSubscription')
            ->with($this->callback(function (CreateStripeSubscriptionDTO $dto) use ($subscription): bool {
                $this->assertSame((string) $subscription->tenant->stripe_id, $dto->customerId);
                $this->assertSame('price_easy_monthly', $dto->priceId);
                $this->assertSame('pm_default_123', $dto->defaultPaymentMethodId);
                $this->assertSame(14, $dto->trialPeriodDays);
                $this->assertNotEmpty($dto->idempotencyKey);

                return true;
            }))
            ->willReturn(new StripeSubscriptionResultDTO(
                id: 'sub_trial_123',
                status: 'trialing',
                trialEnd: now()->addDays(14)->timestamp,
            ));

        $service = new SubscriptionUpgradeBillingService($paymentMethodGuard, $gateway);
        $result = $service->createTrialSubscriptionFromFree($subscription, 'price_easy_monthly', 14);

        $this->assertSame('sub_trial_123', $result->id);
        $this->assertSame('trialing', $result->status);
        $this->assertNotNull($result->trialEnd);
    }

    public function test_create_paid_subscription_from_free_creates_non_trial_subscription(): void
    {
        $subscription = $this->createFreeSubscription();

        $paymentMethodGuard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $paymentMethodGuard->expects($this->once())
            ->method('ensureLiveDefaultPaymentMethod')
            ->willReturn('pm_default_123');

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->once())
            ->method('createSubscription')
            ->with($this->callback(function (CreateStripeSubscriptionDTO $dto) use ($subscription): bool {
                $this->assertSame((string) $subscription->tenant->stripe_id, $dto->customerId);
                $this->assertSame('price_easy_monthly', $dto->priceId);
                $this->assertSame('pm_default_123', $dto->defaultPaymentMethodId);
                $this->assertNull($dto->trialPeriodDays);
                $this->assertNotEmpty($dto->idempotencyKey);

                return true;
            }))
            ->willReturn(new StripeSubscriptionResultDTO(
                id: 'sub_paid_123',
                status: 'active',
            ));

        $service = new SubscriptionUpgradeBillingService($paymentMethodGuard, $gateway);
        $result = $service->createPaidSubscriptionFromFree($subscription, 'price_easy_monthly');

        $this->assertSame('sub_paid_123', $result->id);
        $this->assertSame('active', $result->status);
        $this->assertNull($result->trialEnd);
    }

    public function test_create_trial_subscription_from_free_wraps_gateway_exception(): void
    {
        $subscription = $this->createFreeSubscription();

        $paymentMethodGuard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $paymentMethodGuard->expects($this->once())
            ->method('ensureLiveDefaultPaymentMethod')
            ->willReturn('pm_default_123');

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->once())
            ->method('createSubscription')
            ->willThrowException(new BillingProviderException('stripe unavailable'));

        $service = new SubscriptionUpgradeBillingService($paymentMethodGuard, $gateway);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Stripe error: stripe unavailable');

        $service->createTrialSubscriptionFromFree($subscription, 'price_easy_monthly', 14);
    }

    private function createFreeSubscription(): Subscription
    {
        $freePlan = Plan::factory()->create([
            'slug' => 'free',
            'monthly_price' => 0.00,
            'yearly_price' => 0.00,
        ]);

        $tenant = Tenant::factory()->create([
            'stripe_id' => 'cus_test_123',
        ]);

        return Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $freePlan->id,
            'stripe_id' => 'free_'.$tenant->id,
            'stripe_status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
    }
}
