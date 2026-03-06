<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Services\StripeService;
use App\DTOs\Billing\CreateStripeSubscriptionDTO;
use App\Exceptions\BillingProviderException;
use App\Services\Billing\StripeBillingGateway;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Subscription;
use Tests\TestCase;

final class StripeBillingGatewayTest extends TestCase
{
    public function test_create_portal_session_returns_portal_url(): void
    {
        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->once())
            ->method('createBillingPortalSession')
            ->with('cus_123', 'https://app.termio.sk/billing')
            ->willReturn('https://billing.stripe.com/p/session/test_123');

        $gateway = new StripeBillingGateway($stripeService);
        $portalUrl = $gateway->createPortalSession('cus_123', 'https://app.termio.sk/billing');

        $this->assertSame('https://billing.stripe.com/p/session/test_123', $portalUrl);
    }

    public function test_create_checkout_session_returns_typed_dto(): void
    {
        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->once())
            ->method('createCheckoutSession')
            ->with(['customer' => 'cus_123'])
            ->willReturn(Session::constructFrom([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/test_123',
            ]));

        $gateway = new StripeBillingGateway($stripeService);
        $session = $gateway->createCheckoutSession(['customer' => 'cus_123']);

        $this->assertSame('cs_test_123', $session->sessionId);
        $this->assertSame('https://checkout.stripe.com/c/pay/test_123', $session->url);
    }

    public function test_create_subscription_returns_typed_result(): void
    {
        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->once())
            ->method('createSubscription')
            ->with(
                [
                    'customer' => 'cus_123',
                    'items' => [['price' => 'price_easy_monthly']],
                    'default_payment_method' => 'pm_default_123',
                    'trial_period_days' => 14,
                ],
                ['idempotency_key' => 'subscription-key'],
            )
            ->willReturn(Subscription::constructFrom([
                'id' => 'sub_123',
                'status' => 'trialing',
                'trial_end' => 1_775_000_000,
            ]));

        $gateway = new StripeBillingGateway($stripeService);
        $result = $gateway->createSubscription(new CreateStripeSubscriptionDTO(
            customerId: 'cus_123',
            priceId: 'price_easy_monthly',
            defaultPaymentMethodId: 'pm_default_123',
            trialPeriodDays: 14,
            idempotencyKey: 'subscription-key',
        ));

        $this->assertSame('sub_123', $result->id);
        $this->assertSame('trialing', $result->status);
        $this->assertSame(1_775_000_000, $result->trialEnd);
    }

    public function test_create_subscription_wraps_provider_errors(): void
    {
        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->once())
            ->method('createSubscription')
            ->willThrowException(new RuntimeException('stripe unavailable'));

        $gateway = new StripeBillingGateway($stripeService);

        $this->expectException(BillingProviderException::class);
        $this->expectExceptionMessage('stripe unavailable');

        $gateway->createSubscription(new CreateStripeSubscriptionDTO(
            customerId: 'cus_123',
            priceId: 'price_easy_monthly',
            defaultPaymentMethodId: 'pm_default_123',
        ));
    }
}
