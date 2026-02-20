<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\States\Subscription\CanceledState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CanceledStateTest extends TestCase
{
    use RefreshDatabase;

    private Plan $easyPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTenantWithOwner();
        $this->easyPlan = Plan::factory()->create([
            'name' => 'EASY',
            'slug' => 'easy',
        ]);
    }

    public function test_subscription_show_exposes_canceled_state_during_grace_period(): void
    {
        $days = fake()->numberBetween(2, 14);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => SubscriptionStatus::Active->value,
            'ends_at' => now()->addDays($days),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('subscriptions.show'))
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id);

        $subscription = $subscription->fresh();
        self::assertNotNull($subscription);
        $state = $subscription->getState();

        $this->assertInstanceOf(CanceledState::class, $state);
        $this->assertFalse($subscription->canUpgrade());
        $this->assertFalse($subscription->canDowngrade());
        $this->assertFalse($subscription->canCancel());
        $this->assertTrue($subscription->canResume());
        $this->assertSame('Canceled', $state->getDisplayName());
        $this->assertSame("Subscription ends in {$days} days", $state->getDescription());
        $this->assertSame(['resume'], $subscription->getAvailableActions());
    }

    public function test_subscription_show_returns_singular_day_description_for_canceled_state(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => SubscriptionStatus::Active->value,
            'ends_at' => now()->addDay(),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('subscriptions.show'))
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id);

        $subscription = $subscription->fresh();
        self::assertNotNull($subscription);
        $state = $subscription->getState();

        $this->assertInstanceOf(CanceledState::class, $state);
        $this->assertSame('Subscription ends in 1 day', $state->getDescription());
    }

    public function test_subscription_show_exposes_resubscribe_action_when_grace_period_is_over(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => SubscriptionStatus::Active->value,
            'ends_at' => now()->subDays(fake()->numberBetween(1, 14)),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('subscriptions.show'))
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id);

        $subscription = $subscription->fresh();
        self::assertNotNull($subscription);
        $state = $subscription->getState();

        $this->assertInstanceOf(CanceledState::class, $state);
        $this->assertFalse($subscription->canResume());
        $this->assertSame('Subscription has ended', $state->getDescription());
        $this->assertSame(['resubscribe'], $subscription->getAvailableActions());
    }

    public function test_resume_endpoint_resumes_canceled_subscription_during_grace_period(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_id' => 'free_'.fake()->regexify('[A-Za-z0-9]{16}'),
            'stripe_status' => SubscriptionStatus::Active->value,
            'ends_at' => now()->addDays(fake()->numberBetween(2, 14)),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('subscriptions.resume'))
            ->assertOk()
            ->assertJsonPath('message', 'Subscription resumed successfully.');

        $subscription->refresh();

        $this->assertNull($subscription->ends_at);
        $this->assertSame(SubscriptionStatus::Active, $subscription->stripe_status);
    }

    public function test_resume_endpoint_rejects_when_cancellation_is_already_effective(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_id' => 'free_'.fake()->regexify('[A-Za-z0-9]{16}'),
            'stripe_status' => SubscriptionStatus::Active->value,
            'ends_at' => now()->subDays(fake()->numberBetween(1, 14)),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('subscriptions.resume'))
            ->assertStatus(400)
            ->assertJsonPath('error', 'Cancellation has already taken effect.');
    }
}
