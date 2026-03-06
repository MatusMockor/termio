<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Subscription\Strategies;

use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Contracts\Services\StripeBillingGatewayContract;
use App\Contracts\Services\StripeCustomerProvisionerContract;
use App\DTOs\Billing\CreateStripeSubscriptionDTO;
use App\DTOs\Billing\StripeSubscriptionResultDTO;
use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Enums\SubscriptionType;
use App\Exceptions\BillingProviderException;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Notifications\TrialStartedNotification;
use App\Repositories\Eloquent\EloquentSubscriptionRepository;
use App\Services\Subscription\Strategies\PaidSubscriptionStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class PaidSubscriptionStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_uses_provisioner_guard_and_gateway_and_sends_trial_notification(): void
    {
        Notification::fake();
        $this->createTenantWithOwner();

        $plan = Plan::factory()->create([
            'slug' => 'easy',
            'stripe_monthly_price_id' => 'price_easy_monthly',
            'stripe_yearly_price_id' => 'price_easy_yearly',
        ]);

        $provisioner = $this->createMock(StripeCustomerProvisionerContract::class);
        $provisioner->expects($this->once())
            ->method('ensureCustomerId')
            ->with($this->callback(fn (\App\Models\Tenant $tenant): bool => $tenant->id === $this->tenant->id))
            ->willReturn('cus_123');

        $paymentMethodGuard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $paymentMethodGuard->expects($this->once())
            ->method('ensureLiveDefaultPaymentMethod')
            ->with($this->callback(fn (\App\Models\Tenant $tenant): bool => $tenant->id === $this->tenant->id))
            ->willReturn('pm_default_123');

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->once())
            ->method('createSubscription')
            ->with($this->callback(function (CreateStripeSubscriptionDTO $dto): bool {
                $this->assertSame('cus_123', $dto->customerId);
                $this->assertSame('price_easy_monthly', $dto->priceId);
                $this->assertSame('pm_default_123', $dto->defaultPaymentMethodId);
                $this->assertSame(config('subscription.trial_days'), $dto->trialPeriodDays);

                return true;
            }))
            ->willReturn(new StripeSubscriptionResultDTO(
                id: 'sub_trial_123',
                status: 'trialing',
            ));

        $strategy = new PaidSubscriptionStrategy(
            new EloquentSubscriptionRepository,
            $provisioner,
            $gateway,
            $paymentMethodGuard,
        );

        $subscription = $strategy->create(new CreateSubscriptionDTO(
            tenantId: $this->tenant->id,
            planId: $plan->id,
            billingCycle: 'monthly',
            startTrial: true,
        ), $this->tenant->fresh(), $plan);

        $this->assertSame($this->tenant->id, $subscription->tenant_id);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame(SubscriptionType::Default->value, $subscription->type);
        $this->assertSame('sub_trial_123', $subscription->stripe_id);
        $this->assertSame('trialing', $subscription->stripe_status->value);
        $this->assertSame('price_easy_monthly', $subscription->stripe_price);
        $this->assertNotNull($subscription->trial_ends_at);

        Notification::assertSentTo($this->user, TrialStartedNotification::class);
    }

    public function test_create_wraps_customer_provisioning_failure(): void
    {
        $this->createTenantWithOwner();

        $plan = Plan::factory()->create([
            'slug' => 'easy',
            'stripe_monthly_price_id' => 'price_easy_monthly',
            'stripe_yearly_price_id' => 'price_easy_yearly',
        ]);

        $provisioner = $this->createMock(StripeCustomerProvisionerContract::class);
        $provisioner->expects($this->once())
            ->method('ensureCustomerId')
            ->willThrowException(new BillingProviderException('stripe unavailable'));

        $paymentMethodGuard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $paymentMethodGuard->expects($this->never())
            ->method('ensureLiveDefaultPaymentMethod');

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->never())
            ->method('createSubscription');

        $strategy = new PaidSubscriptionStrategy(
            new EloquentSubscriptionRepository,
            $provisioner,
            $gateway,
            $paymentMethodGuard,
        );

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Stripe error: stripe unavailable');

        $strategy->create(new CreateSubscriptionDTO(
            tenantId: $this->tenant->id,
            planId: $plan->id,
            billingCycle: 'monthly',
            startTrial: true,
        ), $this->tenant->fresh(), $plan);
    }
}
