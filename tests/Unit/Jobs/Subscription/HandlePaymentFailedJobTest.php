<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Subscription;

use App\Jobs\Subscription\HandlePaymentFailedJob;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionDowngradedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class HandlePaymentFailedJobTest extends TestCase
{
    use RefreshDatabase;

    private Plan $freePlan;

    private Plan $smartPlan;

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
    }

    public function test_sends_payment_failed_notification_on_first_attempt(): void
    {
        Notification::fake();

        Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $job = new HandlePaymentFailedJob($this->tenant->id, 1);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        Notification::assertSentTo($this->user, PaymentFailedNotification::class);
        Notification::assertNotSentTo($this->user, SubscriptionDowngradedNotification::class);
    }

    public function test_sends_payment_failed_notification_on_second_attempt(): void
    {
        Notification::fake();

        Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $job = new HandlePaymentFailedJob($this->tenant->id, 2);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        Notification::assertSentTo($this->user, PaymentFailedNotification::class);
        Notification::assertNotSentTo($this->user, SubscriptionDowngradedNotification::class);
    }

    public function test_downgrades_subscription_on_third_attempt(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $job = new HandlePaymentFailedJob($this->tenant->id, 3);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        Notification::assertSentTo($this->user, PaymentFailedNotification::class);
        Notification::assertSentTo($this->user, SubscriptionDowngradedNotification::class);

        $subscription->refresh();
        $this->assertEquals($this->freePlan->id, $subscription->plan_id);
        $this->assertEquals('canceled', $subscription->stripe_status);
    }

    public function test_handles_missing_tenant_gracefully(): void
    {
        Notification::fake();

        $job = new HandlePaymentFailedJob(99999, 1);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        Notification::assertNothingSent();
    }

    public function test_handles_tenant_without_owner_gracefully(): void
    {
        Notification::fake();

        // Delete the owner
        $this->user->delete();

        Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $job = new HandlePaymentFailedJob($this->tenant->id, 3);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        Notification::assertNothingSent();
    }

    public function test_handles_tenant_without_subscription_gracefully(): void
    {
        Notification::fake();

        // No subscription created
        $job = new HandlePaymentFailedJob($this->tenant->id, 3);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        // Should still send payment failed notification
        Notification::assertSentTo($this->user, PaymentFailedNotification::class);
        // But no downgrade notification since no subscription
        Notification::assertNotSentTo($this->user, SubscriptionDowngradedNotification::class);
    }
}
