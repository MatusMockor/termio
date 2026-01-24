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
}
