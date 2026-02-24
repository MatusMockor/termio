<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_clients_list(): void
    {
        $this->actingAsOwner();

        Client::factory()->forTenant($this->tenant)->count(3)->create();

        $response = $this->getJson(route('clients.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'phone', 'email', 'status'],
                ],
            ]);
    }

    public function test_index_filters_by_status(): void
    {
        $this->actingAsOwner();

        Client::factory()->forTenant($this->tenant)->create(['status' => 'active']);
        Client::factory()->forTenant($this->tenant)->vip()->create();
        Client::factory()->forTenant($this->tenant)->inactive()->create();

        $response = $this->getJson(route('clients.index', ['status' => 'vip']));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_supports_pagination(): void
    {
        $this->actingAsOwner();

        Client::factory()->forTenant($this->tenant)->count(3)->create();

        $response = $this->getJson(route('clients.index', ['per_page' => 2]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_store_creates_client(): void
    {
        $this->actingAsOwner();

        $name = fake()->name();
        $phone = fake()->phoneNumber();
        $email = fake()->safeEmail();
        $notes = fake()->sentence();

        $response = $this->postJson(route('clients.store'), [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'notes' => $notes,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.phone', $phone)
            ->assertJsonPath('data.email', $email);

        $this->assertDatabaseHas(Client::class, [
            'name' => $name,
            'phone' => $phone,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('clients.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_show_returns_client_with_recent_appointments(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();

        $response = $this->getJson(route('clients.show', $client));

        $response->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.name', $client->name)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'phone', 'email', 'status', 'appointments'],
            ]);
    }

    public function test_update_modifies_client(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();

        $newName = fake()->name();
        $newPhone = fake()->phoneNumber();

        $response = $this->putJson(route('clients.update', $client), [
            'name' => $newName,
            'phone' => $newPhone,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', $newName)
            ->assertJsonPath('data.phone', $newPhone);
    }

    public function test_update_changes_status_to_vip(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create(['status' => 'active']);

        $response = $this->putJson(route('clients.update', $client), [
            'status' => 'vip',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'vip');
    }

    public function test_destroy_deletes_client(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();

        $response = $this->deleteJson(route('clients.destroy', $client));

        $response->assertNoContent();

        $this->assertSoftDeleted(Client::class, [
            'id' => $client->id,
        ]);
    }

    public function test_search_finds_clients_by_name(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create(['name' => 'John Doe']);
        Client::factory()->forTenant($this->tenant)->create(['name' => 'Jane Smith']);

        $response = $this->getJson(route('clients.search', ['q' => 'John']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'John Doe');
    }

    public function test_search_finds_clients_by_phone(): void
    {
        $this->actingAsOwner();

        $phone = '+421900123456';
        Client::factory()->forTenant($this->tenant)->create(['phone' => $phone]);
        Client::factory()->forTenant($this->tenant)->create(['phone' => '+421900999888']);

        $response = $this->getJson(route('clients.search', ['q' => '123456']));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_search_returns_empty_for_short_query(): void
    {
        $this->actingAsOwner();

        Client::factory()->forTenant($this->tenant)->count(3)->create();

        $response = $this->getJson(route('clients.search', ['q' => 'a']));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_endpoints_require_authentication(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $client = Client::factory()->forTenant($tenant)->create();

        $this->getJson(route('clients.index'))->assertUnauthorized();
        $this->postJson(route('clients.store'))->assertUnauthorized();
        $this->getJson(route('clients.show', $client))->assertUnauthorized();
        $this->putJson(route('clients.update', $client))->assertUnauthorized();
        $this->deleteJson(route('clients.destroy', $client))->assertUnauthorized();
        $this->getJson(route('clients.search', ['q' => 'test']))->assertUnauthorized();
    }
}
