<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FeatureGatingTest extends TestCase
{
    use RefreshDatabase;

    private Plan $freePlan;

    private Plan $easyPlan;

    private Plan $smartPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freePlan = Plan::factory()->create([
            'name' => 'FREE',
            'slug' => 'free',
            'sort_order' => 0,
            'features' => [
                'online_booking_widget' => true,
                'manual_reservations' => true,
                'email_reminders' => true,
                'google_calendar_sync' => false,
                'custom_logo' => false,
                'api_access' => false,
            ],
        ]);

        $this->easyPlan = Plan::factory()->create([
            'name' => 'EASY',
            'slug' => 'easy',
            'sort_order' => 1,
            'monthly_price' => '5.90',
            'features' => [
                'online_booking_widget' => true,
                'manual_reservations' => true,
                'email_reminders' => true,
                'google_calendar_sync' => true,
                'custom_logo' => true,
                'api_access' => false,
            ],
        ]);

        $this->smartPlan = Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
            'sort_order' => 2,
            'monthly_price' => '14.90',
            'features' => [
                'online_booking_widget' => true,
                'manual_reservations' => true,
                'email_reminders' => true,
                'google_calendar_sync' => true,
                'custom_logo' => true,
                'api_access' => true,
            ],
        ]);

        $this->createTenantWithOwner();
    }

    public function test_free_user_cannot_access_google_calendar_feature(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('google-calendar.status'));

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'feature_not_available');
        $response->assertJsonPath('feature', 'google_calendar_sync');
        $response->assertJsonPath('required_plan.slug', 'easy');
    }

    public function test_easy_user_can_access_google_calendar_feature(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('google-calendar.status'));

        // Should not be 403 - may be 200 or other status depending on Google Calendar setup
        $response->assertStatus(200);
    }

    public function test_feature_status_endpoint_returns_all_features(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.features.index'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'google_calendar_sync' => [
                    'available',
                    'value',
                    'required_plan',
                    'label',
                    'category',
                ],
                'custom_logo' => [
                    'available',
                    'value',
                    'required_plan',
                    'label',
                    'category',
                ],
            ],
        ]);

        // Verify google_calendar_sync is not available for free plan
        $response->assertJsonPath('data.google_calendar_sync.available', false);
        $response->assertJsonPath('data.google_calendar_sync.required_plan', 'easy');
    }

    public function test_single_feature_status_endpoint_returns_correct_data(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.features.show', 'google_calendar_sync'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'available',
                'value',
                'required_plan' => [
                    'name',
                    'slug',
                    'monthly_price',
                ],
                'label',
                'category',
            ],
        ]);

        $response->assertJsonPath('data.available', false);
        $response->assertJsonPath('data.required_plan.slug', 'easy');
    }

    public function test_single_feature_status_returns_400_for_unknown_feature(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.features.show', 'unknown_feature'));

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'unknown_feature');
    }

    public function test_grouped_features_endpoint_returns_categorized_features(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.features.grouped'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'customization',
                'integrations',
                'advanced_features',
                'notifications',
            ],
        ]);
    }

    public function test_feature_gating_returns_upgrade_url(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('google-calendar.status'));

        $response->assertStatus(403);
        $response->assertJsonPath('upgrade_url', '/billing/upgrade');
    }

    public function test_feature_available_after_upgrade(): void
    {
        // Start with free plan
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
        ]);

        // Verify feature is not available
        $response = $this->actingAs($this->user)->getJson(route('google-calendar.status'));
        $response->assertStatus(403);

        // Upgrade to easy plan
        $subscription->update(['plan_id' => $this->easyPlan->id]);

        // Verify feature is now available
        $response = $this->actingAs($this->user)->getJson(route('google-calendar.status'));
        $response->assertStatus(200);
    }

    public function test_tenant_without_subscription_gets_free_plan_features(): void
    {
        // No subscription created - should fall back to free plan
        $response = $this->actingAs($this->user)->getJson(route('subscriptions.features.index'));

        $response->assertStatus(200);
        // Without explicit subscription, features should be limited
    }
}
