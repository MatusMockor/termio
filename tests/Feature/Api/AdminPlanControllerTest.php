<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTenantWithOwner();
        $this->adminUser = User::factory()
            ->forTenant($this->tenant)
            ->admin()
            ->create();
    }

    // ==========================================
    // List Plans Tests (Admin)
    // ==========================================

    public function test_admin_can_list_all_plans(): void
    {
        Plan::factory()->count(3)->create(['is_active' => true]);
        Plan::factory()->count(2)->create(['is_active' => false]);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.plans.index'));

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'monthly_price',
                    'yearly_price',
                    'is_active',
                    'is_public',
                    'subscriber_count',
                ],
            ],
        ]);
    }

    public function test_admin_list_includes_inactive_and_hidden_plans(): void
    {
        Plan::factory()->create(['is_active' => true, 'is_public' => true]);
        Plan::factory()->create(['is_active' => false, 'is_public' => true]);
        Plan::factory()->create(['is_active' => true, 'is_public' => false]);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.plans.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_non_admin_cannot_list_plans_via_admin_endpoint(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('admin.plans.index'));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_admin_plan_list(): void
    {
        $response = $this->getJson(route('admin.plans.index'));

        $response->assertUnauthorized();
    }

    // ==========================================
    // Create Plan Tests
    // ==========================================

    public function test_admin_can_create_plan(): void
    {
        $planData = [
            'name' => 'ENTERPRISE',
            'slug' => 'enterprise',
            'description' => fake()->sentence(),
            'monthly_price' => 99.90,
            'yearly_price' => 899.00,
            'features' => [
                'online_booking_widget' => true,
                'google_calendar_sync' => true,
                'api_access' => true,
                'white_label' => true,
            ],
            'limits' => [
                'reservations_per_month' => -1,
                'users' => -1,
                'services' => -1,
            ],
            'sort_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ];

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.plans.store'), $planData);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'ENTERPRISE');
        $response->assertJsonPath('data.slug', 'enterprise');

        $this->assertDatabaseHas(Plan::class, [
            'name' => 'ENTERPRISE',
            'slug' => 'enterprise',
            'monthly_price' => 99.90,
        ]);
    }

    public function test_admin_create_plan_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(route('admin.plans.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'slug', 'monthly_price', 'yearly_price', 'features', 'limits', 'sort_order']);
    }

    public function test_admin_create_plan_validates_unique_slug(): void
    {
        Plan::factory()->create(['slug' => 'existing-slug']);

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.plans.store'), [
            'name' => 'New Plan',
            'slug' => 'existing-slug',
            'monthly_price' => 10.00,
            'yearly_price' => 100.00,
            'features' => [],
            'limits' => [],
            'sort_order' => 1,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_can_create_plan_with_stripe_price_ids(): void
    {
        $planData = [
            'name' => 'STRIPE_PLAN',
            'slug' => 'stripe-plan',
            'monthly_price' => 29.90,
            'yearly_price' => 269.00,
            'features' => ['api_access' => true],
            'limits' => ['users' => 10],
            'sort_order' => 5,
            'stripe_monthly_price_id' => 'price_monthly_123',
            'stripe_yearly_price_id' => 'price_yearly_456',
        ];

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.plans.store'), $planData);

        $response->assertCreated();

        $this->assertDatabaseHas(Plan::class, [
            'slug' => 'stripe-plan',
            'stripe_monthly_price_id' => 'price_monthly_123',
            'stripe_yearly_price_id' => 'price_yearly_456',
        ]);
    }

    public function test_non_admin_cannot_create_plan(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('admin.plans.store'), [
            'name' => 'Unauthorized',
            'slug' => 'unauthorized',
            'monthly_price' => 10.00,
            'yearly_price' => 100.00,
            'features' => [],
            'limits' => [],
            'sort_order' => 1,
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_plan(): void
    {
        $response = $this->postJson(route('admin.plans.store'), [
            'name' => 'Unauthenticated',
            'slug' => 'unauthenticated',
        ]);

        $response->assertUnauthorized();
    }

    // ==========================================
    // Show Plan Tests
    // ==========================================

    public function test_admin_can_view_plan_details(): void
    {
        $plan = Plan::factory()->create([
            'name' => 'DETAIL_PLAN',
            'slug' => 'detail-plan',
            'monthly_price' => 19.90,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.plans.show', $plan->id));

        $response->assertOk();
        $response->assertJsonPath('data.id', $plan->id);
        $response->assertJsonPath('data.name', 'DETAIL_PLAN');
        $response->assertJsonPath('data.subscriber_count', 0);
    }

    public function test_admin_can_view_plan_with_subscriber_count(): void
    {
        $plan = Plan::factory()->create();

        Subscription::factory()->count(5)->create([
            'plan_id' => $plan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.plans.show', $plan->id));

        $response->assertOk();
        $response->assertJsonPath('data.subscriber_count', 5);
    }

    public function test_non_admin_cannot_view_plan_via_admin_endpoint(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)->getJson(route('admin.plans.show', $plan->id));

        $response->assertForbidden();
    }

    // ==========================================
    // Update Plan Tests
    // ==========================================

    public function test_admin_can_update_plan(): void
    {
        $plan = Plan::factory()->create([
            'name' => 'OLD_NAME',
            'monthly_price' => 10.00,
        ]);

        $response = $this->actingAs($this->adminUser)->putJson(route('admin.plans.update', $plan->id), [
            'name' => 'UPDATED_NAME',
            'slug' => $plan->slug,
            'monthly_price' => 15.00,
            'yearly_price' => $plan->yearly_price,
            'features' => $plan->features,
            'limits' => $plan->limits,
            'sort_order' => $plan->sort_order,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'UPDATED_NAME');

        $this->assertDatabaseHas(Plan::class, [
            'id' => $plan->id,
            'name' => 'UPDATED_NAME',
            'monthly_price' => 15.00,
        ]);
    }

    public function test_admin_can_update_plan_features(): void
    {
        $plan = Plan::factory()->create([
            'features' => ['api_access' => false],
        ]);

        $response = $this->actingAs($this->adminUser)->putJson(route('admin.plans.update', $plan->id), [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'monthly_price' => $plan->monthly_price,
            'yearly_price' => $plan->yearly_price,
            'features' => ['api_access' => true, 'white_label' => true],
            'limits' => $plan->limits,
            'sort_order' => $plan->sort_order,
        ]);

        $response->assertOk();

        $plan->refresh();
        $this->assertTrue($plan->features['api_access']);
        $this->assertTrue($plan->features['white_label']);
    }

    public function test_admin_can_update_plan_limits(): void
    {
        $plan = Plan::factory()->create([
            'limits' => ['users' => 5],
        ]);

        $response = $this->actingAs($this->adminUser)->putJson(route('admin.plans.update', $plan->id), [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'monthly_price' => $plan->monthly_price,
            'yearly_price' => $plan->yearly_price,
            'features' => $plan->features,
            'limits' => ['users' => -1, 'services' => 50],
            'sort_order' => $plan->sort_order,
        ]);

        $response->assertOk();

        $plan->refresh();
        $this->assertEquals(-1, $plan->limits['users']);
        $this->assertEquals(50, $plan->limits['services']);
    }

    public function test_non_admin_cannot_update_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)->putJson(route('admin.plans.update', $plan->id), [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertForbidden();
    }

    // ==========================================
    // Deactivate Plan Tests
    // ==========================================

    public function test_admin_can_deactivate_plan(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->adminUser)->deleteJson(route('admin.plans.destroy', $plan->id));

        $response->assertOk();
        $response->assertJsonPath('message', 'Plan deactivated successfully.');

        $plan->refresh();
        $this->assertFalse($plan->is_active);
    }

    public function test_admin_cannot_delete_plan_with_active_subscribers(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        Subscription::factory()->create([
            'plan_id' => $plan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson(route('admin.plans.destroy', $plan->id));

        $response->assertStatus(409);
    }

    public function test_admin_can_deactivate_plan_with_no_subscribers(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->adminUser)->deleteJson(route('admin.plans.destroy', $plan->id));

        $response->assertOk();
    }

    public function test_admin_can_deactivate_plan_with_only_canceled_subscribers(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        Subscription::factory()->create([
            'plan_id' => $plan->id,
            'stripe_status' => 'canceled',
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson(route('admin.plans.destroy', $plan->id));

        $response->assertOk();
    }

    public function test_non_admin_cannot_deactivate_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson(route('admin.plans.destroy', $plan->id));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_deactivate_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->deleteJson(route('admin.plans.destroy', $plan->id));

        $response->assertUnauthorized();
    }

    // ==========================================
    // Statistics Endpoint Tests
    // ==========================================

    public function test_admin_can_view_statistics(): void
    {
        $plan1 = Plan::factory()->create([
            'is_active' => true,
            'monthly_price' => 14.90,
            'yearly_price' => 134.00,
        ]);
        $plan2 = Plan::factory()->create([
            'is_active' => true,
            'monthly_price' => 29.90,
            'yearly_price' => 269.00,
        ]);

        Subscription::factory()->count(3)->create([
            'plan_id' => $plan1->id,
            'stripe_status' => 'active',
            'ends_at' => null,
            'billing_cycle' => 'monthly',
        ]);

        Subscription::factory()->count(2)->create([
            'plan_id' => $plan2->id,
            'stripe_status' => 'active',
            'ends_at' => null,
            'billing_cycle' => 'monthly',
        ]);

        Subscription::factory()->create([
            'plan_id' => $plan1->id,
            'stripe_status' => 'trialing',
            'ends_at' => null,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.plans.statistics'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'total_subscribers',
                'mrr',
                'arr',
                'plans',
                'churn_rate',
                'trial_conversion_rate',
            ],
        ]);
    }

    public function test_non_admin_cannot_access_statistics(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('admin.plans.statistics'));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_statistics(): void
    {
        $response = $this->getJson(route('admin.plans.statistics'));

        $response->assertUnauthorized();
    }

    // ==========================================
    // Edge Cases and Additional Tests
    // ==========================================

    public function test_admin_from_different_tenant_can_still_access_admin_endpoints(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherAdminUser = User::factory()
            ->forTenant($otherTenant)
            ->admin()
            ->create();

        Plan::factory()->count(2)->create();

        $response = $this->actingAs($otherAdminUser)->getJson(route('admin.plans.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_can_view_plans_sorted_by_sort_order(): void
    {
        Plan::factory()->create(['slug' => 'third', 'sort_order' => 3]);
        Plan::factory()->create(['slug' => 'first', 'sort_order' => 1]);
        Plan::factory()->create(['slug' => 'second', 'sort_order' => 2]);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.plans.index'));

        $response->assertOk();
    }
}
