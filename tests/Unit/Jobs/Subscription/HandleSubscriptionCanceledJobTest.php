<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Subscription;

use App\Enums\SubscriptionStatus;
use App\Jobs\Subscription\HandleSubscriptionCanceledJob;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionEndedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class HandleSubscriptionCanceledJobTest extends TestCase
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

    public function test_downgrades_subscription_to_free_plan(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $job = new HandleSubscriptionCanceledJob($subscription->id);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        $subscription->refresh();
        $this->assertEquals($this->freePlan->id, $subscription->plan_id);
        $this->assertEquals(SubscriptionStatus::Canceled, $subscription->stripe_status);
        $this->assertNotNull($subscription->ends_at);
    }

    public function test_clears_scheduled_plan_changes(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->smartPlan)
            ->withScheduledDowngrade($this->freePlan)
            ->create();

        $job = new HandleSubscriptionCanceledJob($subscription->id);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        $subscription->refresh();
        $this->assertNull($subscription->scheduled_plan_id);
        $this->assertNull($subscription->scheduled_change_at);
    }

    public function test_sends_subscription_ended_notification(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $job = new HandleSubscriptionCanceledJob($subscription->id);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        Notification::assertSentTo($this->user, SubscriptionEndedNotification::class);
    }

    public function test_handles_missing_subscription_gracefully(): void
    {
        Notification::fake();

        $job = new HandleSubscriptionCanceledJob(99999);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        Notification::assertNothingSent();
    }

    public function test_handles_subscription_without_owner_gracefully(): void
    {
        Notification::fake();

        // Delete the owner
        $this->user->delete();

        $subscription = Subscription::factory()->forTenant($this->tenant)->forPlan($this->smartPlan)->create();

        $job = new HandleSubscriptionCanceledJob($subscription->id);
        $job->handle(
            app(\App\Contracts\Repositories\SubscriptionRepository::class),
            app(\App\Contracts\Repositories\PlanRepository::class)
        );

        // Subscription should still be downgraded
        $subscription->refresh();
        $this->assertEquals($this->freePlan->id, $subscription->plan_id);

        // But no notification sent
        Notification::assertNothingSent();
    }
}
