<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Billing;

use App\Actions\Billing\CheckoutSessionCreateAction;
use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\StripeBillingGatewayContract;
use App\Contracts\Services\StripeCustomerProvisionerContract;
use App\DTOs\Billing\CheckoutSessionDTO;
use App\Enums\BillingCycle;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CheckoutSessionCreateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_includes_trial_days_when_no_active_subscription_exists(): void
    {
        $this->createTenantWithOwner();
        $plan = Plan::factory()->create([
            'stripe_monthly_price_id' => 'price_easy_monthly',
            'stripe_yearly_price_id' => 'price_easy_yearly',
        ]);

        $provisioner = $this->createMock(StripeCustomerProvisionerContract::class);
        $provisioner->expects($this->once())
            ->method('ensureCustomerId')
            ->with($this->callback(fn (\App\Models\Tenant $tenant): bool => $tenant->id === $this->tenant->id))
            ->willReturn('cus_123');

        $plans = $this->createMock(PlanRepository::class);
        $plans->expects($this->once())
            ->method('findById')
            ->with($plan->id)
            ->willReturn($plan);

        $subscriptions = $this->createMock(SubscriptionRepository::class);
        $subscriptions->expects($this->once())
            ->method('findActiveByTenant')
            ->with($this->callback(fn (\App\Models\Tenant $tenant): bool => $tenant->id === $this->tenant->id))
            ->willReturn(null);

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->once())
            ->method('createCheckoutSession')
            ->with($this->callback(function (array $params) use ($plan): bool {
                $this->assertSame('cus_123', $params['customer']);
                $this->assertSame('price_easy_monthly', $params['line_items'][0]['price']);
                $this->assertSame((string) $this->tenant->id, $params['subscription_data']['metadata']['tenant_id']);
                $this->assertSame((string) $plan->id, $params['subscription_data']['metadata']['plan_id']);
                $this->assertSame(BillingCycle::Monthly->value, $params['subscription_data']['metadata']['billing_cycle']);
                $this->assertSame(config('subscription.trial_days'), $params['subscription_data']['trial_period_days']);

                return true;
            }))
            ->willReturn(new CheckoutSessionDTO(
                url: 'https://checkout.stripe.com/c/pay/test_123',
                sessionId: 'cs_test_123',
            ));

        $action = new CheckoutSessionCreateAction($provisioner, $gateway, $plans, $subscriptions);
        $result = $action->handle(
            $this->tenant->fresh(),
            $plan->id,
            BillingCycle::Monthly,
            'https://app.termio.sk/billing/success',
            'https://app.termio.sk/billing/cancel',
        );

        $this->assertSame('cs_test_123', $result->sessionId);
        $this->assertSame('https://checkout.stripe.com/c/pay/test_123', $result->url);
    }

    public function test_handle_skips_trial_days_for_existing_paid_subscription(): void
    {
        $this->createTenantWithOwner();
        $plan = Plan::factory()->create([
            'stripe_monthly_price_id' => 'price_easy_monthly',
            'stripe_yearly_price_id' => 'price_easy_yearly',
        ]);
        $activeSubscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'stripe_id' => 'sub_paid_123',
            'billing_cycle' => BillingCycle::Monthly->value,
        ]);

        $provisioner = $this->createMock(StripeCustomerProvisionerContract::class);
        $provisioner->expects($this->once())
            ->method('ensureCustomerId')
            ->willReturn('cus_123');

        $plans = $this->createMock(PlanRepository::class);
        $plans->expects($this->once())
            ->method('findById')
            ->with($plan->id)
            ->willReturn($plan);

        $subscriptions = $this->createMock(SubscriptionRepository::class);
        $subscriptions->expects($this->once())
            ->method('findActiveByTenant')
            ->willReturn($activeSubscription);

        $gateway = $this->createMock(StripeBillingGatewayContract::class);
        $gateway->expects($this->once())
            ->method('createCheckoutSession')
            ->with($this->callback(function (array $params): bool {
                $this->assertArrayNotHasKey('trial_period_days', $params['subscription_data']);

                return true;
            }))
            ->willReturn(new CheckoutSessionDTO(
                url: 'https://checkout.stripe.com/c/pay/test_456',
                sessionId: 'cs_test_456',
            ));

        $action = new CheckoutSessionCreateAction($provisioner, $gateway, $plans, $subscriptions);
        $result = $action->handle(
            $this->tenant->fresh(),
            $plan->id,
            BillingCycle::Monthly,
            'https://app.termio.sk/billing/success',
            'https://app.termio.sk/billing/cancel',
        );

        $this->assertSame('cs_test_456', $result->sessionId);
        $this->assertSame('https://checkout.stripe.com/c/pay/test_456', $result->url);
    }
}
