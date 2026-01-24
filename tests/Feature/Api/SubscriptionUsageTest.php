<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionUsageTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::factory()->create([
            'slug' => 'free',
            'name' => 'Free',
            'limits' => [
                'reservations_per_month' => 100,
                'users' => 5,
                'services' => 10,
            ],
        ]);

        $this->createTenantWithOwner();

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'stripe_status' => 'active',
        ]);
    }

    public function test_can_get_usage_stats(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 25,
            'reservations_limit' => 100,
        ]);

        Service::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.usage'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'reservations' => ['current', 'limit', 'percentage'],
                'users' => ['current', 'limit', 'percentage'],
                'services' => ['current', 'limit', 'percentage'],
            ],
            'warnings' => [
                'reservations',
                'users',
                'services',
            ],
        ]);

        $this->assertEquals(25, $response->json('data.reservations.current'));
        $this->assertEquals(100, $response->json('data.reservations.limit'));
        $this->assertEquals(25.0, $response->json('data.reservations.percentage'));
        $this->assertEquals(3, $response->json('data.services.current'));
    }

    public function test_usage_stats_shows_warnings_when_near_limit(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 85,
            'reservations_limit' => 100,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.usage'));

        $response->assertOk();
        $response->assertJsonPath('warnings.reservations', true);
    }

    public function test_usage_stats_no_warnings_when_under_threshold(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 50,
            'reservations_limit' => 100,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.usage'));

        $response->assertOk();
        $response->assertJsonPath('warnings.reservations', false);
    }

    public function test_usage_stats_requires_authentication(): void
    {
        $response = $this->getJson(route('subscriptions.usage'));

        $response->assertUnauthorized();
    }

    public function test_usage_stats_requires_owner_role(): void
    {
        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->getJson(route('subscriptions.usage'));

        $response->assertForbidden();
    }
}
