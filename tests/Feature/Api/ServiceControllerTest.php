<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_services_list(): void
    {
        $this->actingAsOwner();

        Service::factory()->forTenant($this->tenant)->count(3)->create();

        $response = $this->getJson(route('services.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'duration_minutes', 'price', 'is_active'],
                ],
            ]);
    }

    public function test_store_creates_service(): void
    {
        $this->actingAsOwner();

        $name = fake()->words(3, true);
        $description = fake()->sentence();
        $durationMinutes = fake()->randomElement([30, 45, 60, 90]);
        $price = fake()->randomFloat(2, 10, 100);
        $category = fake()->word();

        $response = $this->postJson(route('services.store'), [
            'name' => $name,
            'description' => $description,
            'duration_minutes' => $durationMinutes,
            'price' => $price,
            'category' => $category,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.duration_minutes', $durationMinutes);

        $this->assertDatabaseHas(Service::class, [
            'name' => $name,
            'duration_minutes' => $durationMinutes,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_index_supports_pagination(): void
    {
        $this->actingAsOwner();

        Service::factory()->forTenant($this->tenant)->count(3)->create();

        $response = $this->getJson(route('services.index', ['per_page' => 2]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('services.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'duration_minutes', 'price']);
    }

    public function test_store_validates_duration_range(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('services.store'), [
            'name' => fake()->words(3, true),
            'duration_minutes' => 3,
            'price' => 10,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['duration_minutes']);
    }

    public function test_show_returns_service(): void
    {
        $this->actingAsOwner();

        $service = Service::factory()->forTenant($this->tenant)->create();

        $response = $this->getJson(route('services.show', $service));

        $response->assertOk()
            ->assertJsonPath('data.id', $service->id)
            ->assertJsonPath('data.name', $service->name);
    }

    public function test_update_modifies_service(): void
    {
        $this->actingAsOwner();

        $service = Service::factory()->forTenant($this->tenant)->create();

        $newName = fake()->words(3, true);
        $newPrice = 45.50;

        $response = $this->putJson(route('services.update', $service), [
            'name' => $newName,
            'price' => $newPrice,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', $newName);

        $this->assertEquals($newPrice, (float) $response->json('data.price'));
    }

    public function test_destroy_deletes_service(): void
    {
        $this->actingAsOwner();

        $service = Service::factory()->forTenant($this->tenant)->create();

        $response = $this->deleteJson(route('services.destroy', $service));

        $response->assertNoContent();

        $this->assertSoftDeleted(Service::class, [
            'id' => $service->id,
        ]);
    }

    public function test_reorder_updates_sort_order(): void
    {
        $this->actingAsOwner();

        $service1 = Service::factory()->forTenant($this->tenant)->create(['sort_order' => 0]);
        $service2 = Service::factory()->forTenant($this->tenant)->create(['sort_order' => 1]);
        $service3 = Service::factory()->forTenant($this->tenant)->create(['sort_order' => 2]);

        $response = $this->postJson(route('services.reorder'), [
            'order' => [$service3->id, $service1->id, $service2->id],
        ]);

        $response->assertOk();

        $this->assertEquals(0, $service3->fresh()->sort_order);
        $this->assertEquals(1, $service1->fresh()->sort_order);
        $this->assertEquals(2, $service2->fresh()->sort_order);
    }

    public function test_endpoints_require_authentication(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $service = Service::factory()->forTenant($tenant)->create();

        $this->getJson(route('services.index'))->assertUnauthorized();
        $this->postJson(route('services.store'))->assertUnauthorized();
        $this->getJson(route('services.show', $service))->assertUnauthorized();
        $this->putJson(route('services.update', $service))->assertUnauthorized();
        $this->deleteJson(route('services.destroy', $service))->assertUnauthorized();
        $this->postJson(route('services.reorder'))->assertUnauthorized();
    }
}
