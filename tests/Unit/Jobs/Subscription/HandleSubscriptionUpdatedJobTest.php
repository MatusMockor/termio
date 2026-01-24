<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Subscription;

use App\Jobs\Subscription\HandleSubscriptionUpdatedJob;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HandleSubscriptionUpdatedJobTest extends TestCase
{
    use RefreshDatabase;

    private Plan $smartPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTenantWithOwner();

        $this->smartPlan = Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
            'monthly_price' => 14.90,
        ]);
    }

    public function test_updates_subscription_status(): void
    {
        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->smartPlan)
            ->create(['stripe_status' => 'trialing']);

        $job = new HandleSubscriptionUpdatedJob(
            $subscription->id,
            'active',
            []
        );
        $job->handle(app(\App\Contracts\Repositories\SubscriptionRepository::class));

        $subscription->refresh();
        $this->assertEquals('active', $subscription->stripe_status);
    }

    public function test_updates_trial_end_date(): void
    {
        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->smartPlan)
            ->create(['trial_ends_at' => null]);

        $trialEndTimestamp = now()->addDays(14)->timestamp;

        $job = new HandleSubscriptionUpdatedJob(
            $subscription->id,
            'trialing',
            ['trial_end' => $trialEndTimestamp]
        );
        $job->handle(app(\App\Contracts\Repositories\SubscriptionRepository::class));

        $subscription->refresh();
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertEquals($trialEndTimestamp, $subscription->trial_ends_at->timestamp);
    }

    public function test_updates_cancel_at_date(): void
    {
        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->smartPlan)
            ->create(['ends_at' => null]);

        $cancelAtTimestamp = now()->addMonth()->timestamp;

        $job = new HandleSubscriptionUpdatedJob(
            $subscription->id,
            'active',
            ['cancel_at' => $cancelAtTimestamp]
        );
        $job->handle(app(\App\Contracts\Repositories\SubscriptionRepository::class));

        $subscription->refresh();
        $this->assertNotNull($subscription->ends_at);
        $this->assertEquals($cancelAtTimestamp, $subscription->ends_at->timestamp);
    }

    public function test_updates_ends_at_from_period_end_when_canceled(): void
    {
        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->smartPlan)
            ->create(['ends_at' => null]);

        $periodEndTimestamp = now()->addMonth()->timestamp;

        $job = new HandleSubscriptionUpdatedJob(
            $subscription->id,
            'active',
            [
                'canceled_at' => now()->timestamp,
                'cancel_at_period_end' => true,
                'current_period_end' => $periodEndTimestamp,
            ]
        );
        $job->handle(app(\App\Contracts\Repositories\SubscriptionRepository::class));

        $subscription->refresh();
        $this->assertNotNull($subscription->ends_at);
        $this->assertEquals($periodEndTimestamp, $subscription->ends_at->timestamp);
    }

    public function test_updates_stripe_price(): void
    {
        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->smartPlan)
            ->create(['stripe_price' => 'price_old']);

        $job = new HandleSubscriptionUpdatedJob(
            $subscription->id,
            'active',
            [
                'items' => [
                    'data' => [
                        [
                            'price' => [
                                'id' => 'price_new123',
                            ],
                        ],
                    ],
                ],
            ]
        );
        $job->handle(app(\App\Contracts\Repositories\SubscriptionRepository::class));

        $subscription->refresh();
        $this->assertEquals('price_new123', $subscription->stripe_price);
    }

    public function test_handles_missing_subscription_gracefully(): void
    {
        $job = new HandleSubscriptionUpdatedJob(99999, 'active', []);
        $job->handle(app(\App\Contracts\Repositories\SubscriptionRepository::class));

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function test_handles_missing_items_data_gracefully(): void
    {
        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->smartPlan)
            ->create(['stripe_price' => 'price_original']);

        $job = new HandleSubscriptionUpdatedJob(
            $subscription->id,
            'active',
            ['items' => ['data' => []]]
        );
        $job->handle(app(\App\Contracts\Repositories\SubscriptionRepository::class));

        $subscription->refresh();
        // Price should remain unchanged
        $this->assertEquals('price_original', $subscription->stripe_price);
    }
}
