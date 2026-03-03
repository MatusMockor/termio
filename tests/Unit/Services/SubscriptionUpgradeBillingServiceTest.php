<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscription\SubscriptionUpgradeBillingService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Stripe\StripeClient;
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

        $service = new SubscriptionUpgradeBillingService($paymentMethodGuard);

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

        $service = new SubscriptionUpgradeBillingService($paymentMethodGuard);

        $capturedPayload = [];
        $capturedOptions = [];

        $this->bindStripeClientFake(
            static function (array $payload, array $options) use (&$capturedPayload, &$capturedOptions): object {
                $capturedPayload = $payload;
                $capturedOptions = $options;

                return (object) [
                    'id' => 'sub_trial_123',
                    'status' => 'trialing',
                    'trial_end' => now()->addDays(14)->timestamp,
                ];
            },
        );

        $result = $service->createTrialSubscriptionFromFree($subscription, 'price_easy_monthly', 14);

        $this->assertSame('sub_trial_123', $result->id);
        $this->assertSame('trialing', $result->status);
        $this->assertSame((string) $subscription->tenant->stripe_id, $capturedPayload['customer']);
        $this->assertSame('price_easy_monthly', $capturedPayload['items'][0]['price']);
        $this->assertSame('pm_default_123', $capturedPayload['default_payment_method']);
        $this->assertSame(14, $capturedPayload['trial_period_days']);
        $this->assertArrayHasKey('idempotency_key', $capturedOptions);
        $this->assertNotEmpty($capturedOptions['idempotency_key']);
    }

    public function test_create_trial_subscription_from_free_wraps_stripe_exception(): void
    {
        $subscription = $this->createFreeSubscription();

        $paymentMethodGuard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $paymentMethodGuard->expects($this->once())
            ->method('ensureLiveDefaultPaymentMethod')
            ->willReturn('pm_default_123');

        $service = new SubscriptionUpgradeBillingService($paymentMethodGuard);

        $this->bindStripeClientFake(
            static function (): never {
                throw new RuntimeException('stripe unavailable');
            },
        );

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

    /**
     * @param  callable(array<string, mixed>, array<string, string>): object  $createSubscription
     */
    private function bindStripeClientFake(callable $createSubscription): void
    {
        $this->app->bind(StripeClient::class, static function () use ($createSubscription): object {
            $subscriptionsService = new class($createSubscription)
            {
                /**
                 * @param  callable(array<string, mixed>, array<string, string>): object  $createSubscription
                 */
                public function __construct(
                    callable $createSubscription,
                ) {
                    $this->createSubscription = Closure::fromCallable($createSubscription);
                }

                private readonly Closure $createSubscription;

                /**
                 * @param  array<string, mixed>  $payload
                 * @param  array<string, string>  $options
                 */
                public function create(array $payload, array $options = []): object
                {
                    return ($this->createSubscription)($payload, $options);
                }
            };

            return (object) [
                'subscriptions' => $subscriptionsService,
            ];
        });
    }
}
