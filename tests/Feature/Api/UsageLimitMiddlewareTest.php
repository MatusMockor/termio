<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\UsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UsageLimitMiddlewareTest extends TestCase
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
                'reservations_per_month' => 10,
                'users' => 2,
                'services' => 5,
            ],
        ]);

        $this->createTenantWithOwner();

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'stripe_status' => 'active',
        ]);
    }

    public function test_reservation_creation_blocked_when_limit_reached(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 10,
            'reservations_limit' => 10,
        ]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('appointments.store'), [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => now()->addDay()->setHour(10)->toDateTimeString(),
            'source' => 'manual',
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('error', 'reservation_limit_exceeded');
    }

    public function test_reservation_creation_allowed_when_under_limit(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 5,
            'reservations_limit' => 10,
        ]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('appointments.store'), [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => now()->addDay()->setHour(10)->toDateTimeString(),
            'source' => 'manual',
        ]);

        $response->assertStatus(201);
    }

    public function test_service_creation_blocked_when_limit_reached(): void
    {
        // Create services up to the limit
        Service::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('services.store'), [
            'name' => fake()->words(2, true),
            'duration_minutes' => 60,
            'price' => fake()->randomFloat(2, 10, 100),
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('error', 'service_limit_exceeded');
    }

    public function test_service_creation_allowed_when_under_limit(): void
    {
        // Create fewer services than the limit
        Service::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('services.store'), [
            'name' => fake()->words(2, true),
            'duration_minutes' => 60,
            'price' => fake()->randomFloat(2, 10, 100),
        ]);

        $response->assertStatus(201);
    }

    public function test_usage_warning_header_added_at_80_percent(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 8, // 80% of 10
            'reservations_limit' => 10,
        ]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('appointments.store'), [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => now()->addDay()->setHour(10)->toDateTimeString(),
            'source' => 'manual',
        ]);

        $response->assertStatus(201);
        $response->assertHeader('X-Usage-Warning');
    }

    public function test_unlimited_plan_has_no_reservation_limit(): void
    {
        // Update plan to have unlimited reservations
        $unlimitedPlan = Plan::factory()->create([
            'slug' => 'unlimited',
            'name' => 'Unlimited',
            'limits' => [
                'reservations_per_month' => -1,
                'users' => -1,
                'services' => -1,
            ],
        ]);

        $this->tenant->localSubscription()->delete();

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $unlimitedPlan->id,
            'stripe_status' => 'active',
        ]);

        // Create a large usage record - should not block
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 10000,
            'reservations_limit' => -1,
        ]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('appointments.store'), [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => now()->addDay()->setHour(10)->toDateTimeString(),
            'source' => 'manual',
        ]);

        $response->assertStatus(201);
    }

    public function test_unlimited_plan_has_no_service_limit(): void
    {
        $unlimitedPlan = Plan::factory()->create([
            'slug' => 'unlimited-services',
            'name' => 'Unlimited Services',
            'limits' => [
                'reservations_per_month' => -1,
                'users' => -1,
                'services' => -1,
            ],
        ]);

        $this->tenant->localSubscription()->delete();

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $unlimitedPlan->id,
            'stripe_status' => 'active',
        ]);

        // Create many services
        Service::factory()->count(50)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('services.store'), [
            'name' => fake()->words(2, true),
            'duration_minutes' => 60,
            'price' => fake()->randomFloat(2, 10, 100),
        ]);

        $response->assertStatus(201);
    }

    public function test_usage_stats_show_correct_percentage(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 5,
            'reservations_limit' => 10,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.usage'));

        $response->assertOk();
        $this->assertEquals(50.0, $response->json('data.reservations.percentage'));
    }

    public function test_usage_stats_no_warning_at_79_percent(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 7, // 70% of 10
            'reservations_limit' => 10,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.usage'));

        $response->assertOk();
        $response->assertJsonPath('warnings.reservations', false);
    }

    public function test_usage_stats_warning_shown_at_80_percent(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 8, // 80% of 10
            'reservations_limit' => 10,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.usage'));

        $response->assertOk();
        $response->assertJsonPath('warnings.reservations', true);
    }

    public function test_reservation_creation_at_exact_limit_is_blocked(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 10, // Exactly at limit
            'reservations_limit' => 10,
        ]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('appointments.store'), [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => now()->addDay()->setHour(10)->toDateTimeString(),
            'source' => 'manual',
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('error', 'reservation_limit_exceeded');
    }

    public function test_reservation_creation_one_below_limit_is_allowed(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 9, // One below limit
            'reservations_limit' => 10,
        ]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('appointments.store'), [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => now()->addDay()->setHour(10)->toDateTimeString(),
            'source' => 'manual',
        ]);

        $response->assertStatus(201);
    }

    public function test_service_creation_at_exact_limit_is_blocked(): void
    {
        // Create exactly 5 services (the limit)
        Service::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('services.store'), [
            'name' => fake()->words(2, true),
            'duration_minutes' => 30,
            'price' => fake()->randomFloat(2, 5, 50),
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('error', 'service_limit_exceeded');
    }

    public function test_service_creation_one_below_limit_is_allowed(): void
    {
        // Create 4 services (one below limit of 5)
        Service::factory()->count(4)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('services.store'), [
            'name' => fake()->words(2, true),
            'duration_minutes' => 45,
            'price' => fake()->randomFloat(2, 10, 100),
        ]);

        $response->assertStatus(201);
    }

    public function test_usage_limit_response_includes_upgrade_message(): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'period' => now()->format('Y-m'),
            'reservations_count' => 10,
            'reservations_limit' => 10,
        ]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('appointments.store'), [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => now()->addDay()->setHour(10)->toDateTimeString(),
            'source' => 'manual',
        ]);

        $response->assertStatus(402);
        $response->assertJsonStructure(['error', 'message']);
        $this->assertStringContainsString('upgrade', $response->json('message'));
    }
}
