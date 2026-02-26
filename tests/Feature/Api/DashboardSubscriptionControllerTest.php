<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_get_dashboard_subscription_context(): void
    {
        $this->actingAsOwner();
        [, $easyPlan, $smartPlan] = $this->createPlans();

        $this->tenant->update(['pm_type' => 'visa']);

        Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($easyPlan)
            ->create([
                'stripe_status' => 'active',
                'stripe_id' => 'sub_dashboard_context',
                'billing_cycle' => 'monthly',
            ]);

        $response = $this->getJson(route('dashboard.subscription'));

        $response->assertOk()
            ->assertJsonPath('data.current_plan.id', $easyPlan->id)
            ->assertJsonPath('data.current_plan.slug', $easyPlan->slug)
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.billing_cycle', 'monthly')
            ->assertJsonPath('data.subscription.is_free_subscription', false)
            ->assertJsonPath('data.recommended_plan_id', $smartPlan->id)
            ->assertJsonPath('data.actions.next_action', 'upgrade')
            ->assertJsonPath('data.actions.requires_default_payment_method', false)
            ->assertJsonPath('data.actions.has_default_payment_method', true)
            ->assertJsonCount(1, 'data.upgrade_options')
            ->assertJsonPath('data.upgrade_options.0.id', $smartPlan->id);

    }

    public function test_staff_cannot_access_dashboard_subscription_context(): void
    {
        $this->createTenantWithOwner();
        $this->createPlans();

        $staffUser = User::factory()->forTenant($this->tenant)->staff()->create();

        $response = $this->actingAs($staffUser)->getJson(route('dashboard.subscription'));

        $response->assertForbidden();
    }

    public function test_dashboard_subscription_context_requires_authentication(): void
    {
        $response = $this->getJson(route('dashboard.subscription'));

        $response->assertUnauthorized();
    }

    public function test_no_active_subscription_returns_create_next_action(): void
    {
        $this->actingAsOwner();
        [$freePlan] = $this->createPlans();

        $response = $this->getJson(route('dashboard.subscription'));

        $response->assertOk()
            ->assertJsonPath('data.current_plan.slug', $freePlan->slug)
            ->assertJsonPath('data.subscription.id', null)
            ->assertJsonPath('data.actions.next_action', 'create')
            ->assertJsonPath('data.actions.default_billing_cycle', 'monthly');
    }

    public function test_highest_plan_returns_no_upgrade_action(): void
    {
        $this->actingAsOwner();
        [, , $smartPlan] = $this->createPlans();

        Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($smartPlan)
            ->create([
                'stripe_status' => 'active',
                'stripe_id' => 'sub_highest_plan',
                'billing_cycle' => 'monthly',
            ]);

        $response = $this->getJson(route('dashboard.subscription'));

        $response->assertOk()
            ->assertJsonCount(0, 'data.upgrade_options')
            ->assertJsonPath('data.recommended_plan_id', null)
            ->assertJsonPath('data.actions.next_action', 'none');
    }

    public function test_dashboard_subscription_context_excludes_non_public_or_inactive_plans(): void
    {
        $this->actingAsOwner();
        [, $easyPlan, $smartPlan] = $this->createPlans();

        Plan::factory()->create([
            'name' => 'HIDDEN_PLUS',
            'slug' => 'hidden-plus',
            'sort_order' => 3,
            'is_active' => true,
            'is_public' => false,
        ]);

        Plan::factory()->create([
            'name' => 'INACTIVE_PLUS',
            'slug' => 'inactive-plus',
            'sort_order' => 4,
            'is_active' => false,
            'is_public' => true,
        ]);

        Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($easyPlan)
            ->create([
                'stripe_status' => 'active',
                'stripe_id' => 'sub_filter_plans',
                'billing_cycle' => 'monthly',
            ]);

        $response = $this->getJson(route('dashboard.subscription'));

        $response->assertOk()
            ->assertJsonCount(1, 'data.upgrade_options')
            ->assertJsonPath('data.upgrade_options.0.slug', $smartPlan->slug);
    }

    /**
     * @return array{Plan, Plan, Plan}
     */
    private function createPlans(): array
    {
        $freePlan = Plan::factory()->create([
            'name' => 'FREE',
            'slug' => 'free',
            'monthly_price' => 0.00,
            'yearly_price' => 0.00,
            'sort_order' => 0,
            'is_active' => true,
            'is_public' => true,
        ]);

        $easyPlan = Plan::factory()->create([
            'name' => 'EASY',
            'slug' => 'easy',
            'monthly_price' => 5.90,
            'yearly_price' => 53.00,
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => true,
            'stripe_monthly_price_id' => 'price_easy_monthly',
            'stripe_yearly_price_id' => 'price_easy_yearly',
        ]);

        $smartPlan = Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
            'monthly_price' => 14.90,
            'yearly_price' => 134.00,
            'sort_order' => 2,
            'is_active' => true,
            'is_public' => true,
            'stripe_monthly_price_id' => 'price_smart_monthly',
            'stripe_yearly_price_id' => 'price_smart_yearly',
        ]);

        return [$freePlan, $easyPlan, $smartPlan];
    }
}
