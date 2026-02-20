<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Subscription;
use App\States\Subscription\PastDueState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PastDueStateTest extends TestCase
{
    use RefreshDatabase;

    private Plan $smartPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTenantWithOwner();
        Plan::factory()->create([
            'name' => 'FREE',
            'slug' => 'free',
            'monthly_price' => 0.00,
            'yearly_price' => 0.00,
        ]);

        $this->smartPlan = Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
        ]);
    }

    public function test_subscription_show_handles_past_due_subscription_in_real_flow(): void
    {
        $subscription = Subscription::factory()->pastDue()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->smartPlan->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.show'));

        $response->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('plan.slug', 'free');

        $this->assertNotNull($subscription->fresh());
    }

    public function test_past_due_state_behavior_is_accessible_via_subscription_endpoint_flow(): void
    {
        $subscription = Subscription::factory()->pastDue()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->smartPlan->id,
        ]);

        $this->actingAs($this->user)->getJson(route('subscriptions.show'))->assertOk();

        $subscription = $subscription->fresh();
        self::assertNotNull($subscription);
        $state = $subscription->getState();

        $this->assertInstanceOf(PastDueState::class, $state);
        $this->assertFalse($subscription->canUpgrade());
        $this->assertFalse($subscription->canDowngrade());
        $this->assertTrue($subscription->canCancel());
        $this->assertFalse($subscription->canResume());
        $this->assertSame('Past Due', $state->getDisplayName());
        $this->assertSame('Payment failed. Please update your payment method.', $state->getDescription());
        $this->assertSame(['update_payment_method', 'cancel'], $subscription->getAvailableActions());
    }
}
