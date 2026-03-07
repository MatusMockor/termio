<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\ClientTag;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
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
                    '*' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'status',
                        'tags',
                        'booking_controls',
                        'anti_no_show',
                        'can_book_online',
                    ],
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
            'phone_normalized' => preg_replace('/\D+/', '', $phone),
            'email_normalized' => mb_strtolower($email),
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
                'data' => [
                    'id',
                    'name',
                    'phone',
                    'email',
                    'status',
                    'tags',
                    'booking_controls',
                    'anti_no_show',
                    'can_book_online',
                    'appointments',
                ],
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

    public function test_owner_can_sync_tags_to_client(): void
    {
        $this->actingAsOwner();
        $this->enableFeatures(['client_segmentation' => true]);

        $client = Client::factory()->forTenant($this->tenant)->create();
        $firstTag = ClientTag::factory()->forTenant($this->tenant)->create();
        $secondTag = ClientTag::factory()->forTenant($this->tenant)->create();

        $response = $this->putJson(route('clients.tags.sync', $client), [
            'tag_ids' => [$firstTag->id, $secondTag->id],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.tags');

        $this->assertDatabaseHas('client_tag_assignments', [
            'client_id' => $client->id,
            'client_tag_id' => $firstTag->id,
        ]);
    }

    public function test_owner_can_update_booking_controls(): void
    {
        $this->actingAsOwner();
        $this->enableFeatures(['client_segmentation' => true]);

        $client = Client::factory()->forTenant($this->tenant)->create();

        $response = $this->putJson(route('clients.booking-controls.update', $client), [
            'is_blacklisted' => true,
            'is_whitelisted' => false,
            'booking_control_note' => 'Repeated no-shows',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.booking_controls.is_blacklisted', true)
            ->assertJsonPath('data.booking_controls.booking_control_note', 'Repeated no-shows')
            ->assertJsonPath('data.can_book_online', false);
    }

    public function test_index_filters_by_booking_state(): void
    {
        $this->actingAsOwner();

        Client::factory()->forTenant($this->tenant)->create([
            'is_blacklisted' => true,
        ]);
        Client::factory()->forTenant($this->tenant)->create([
            'is_whitelisted' => true,
        ]);
        Client::factory()->forTenant($this->tenant)->create();

        $response = $this->getJson(route('clients.index', ['booking_state' => 'blacklisted']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_controls.is_blacklisted', true);
    }

    public function test_index_filters_by_risk_level(): void
    {
        $this->actingAsOwner();

        Client::factory()->forTenant($this->tenant)->create([
            'no_show_count' => 2,
        ]);
        Client::factory()->forTenant($this->tenant)->create([
            'late_cancellation_count' => 2,
        ]);
        Client::factory()->forTenant($this->tenant)->create();

        $response = $this->getJson(route('clients.index', ['risk_level' => 'high']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.anti_no_show.risk_level', 'high');
    }

    public function test_index_filters_by_tag_ids(): void
    {
        $this->actingAsOwner();
        $this->enableFeatures(['client_segmentation' => true]);

        $tag = ClientTag::factory()->forTenant($this->tenant)->create();
        $matchingClient = Client::factory()->forTenant($this->tenant)->create();
        $nonMatchingClient = Client::factory()->forTenant($this->tenant)->create();
        $matchingClient->tags()->attach($tag->id);

        $response = $this->getJson(route('clients.index', ['tag_ids' => [$tag->id]]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingClient->id);

        $this->assertNotSame($matchingClient->id, $nonMatchingClient->id);
    }

    public function test_cross_tenant_tag_and_booking_controls_access_is_blocked(): void
    {
        $this->actingAsOwner();
        $this->enableFeatures(['client_segmentation' => true]);

        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->forTenant($otherTenant)->create();
        $currentTenantTag = ClientTag::factory()->forTenant($this->tenant)->create();

        $this->putJson(route('clients.tags.sync', $otherClient), [
            'tag_ids' => [$currentTenantTag->id],
        ])->assertNotFound();

        $this->putJson(route('clients.booking-controls.update', $otherClient), [
            'is_blacklisted' => true,
            'is_whitelisted' => false,
            'booking_control_note' => 'Blocked',
        ])->assertNotFound();
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

        Client::factory()->forTenant($this->tenant)->create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+421900111111',
        ]);
        Client::factory()->forTenant($this->tenant)->create([
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'phone' => '+421900222222',
        ]);

        $response = $this->getJson(route('clients.search', ['q' => 'John Doe']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'John Doe');
    }

    public function test_search_finds_clients_by_phone(): void
    {
        $this->actingAsOwner();

        $phone = '+421 900 123 456';
        Client::factory()->forTenant($this->tenant)->create(['phone' => $phone]);
        Client::factory()->forTenant($this->tenant)->create(['phone' => '+421900999888']);

        $response = $this->getJson(route('clients.search', ['q' => '+421-900-123-456']));

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

    /**
     * @param  array<string, bool>  $features
     */
    private function enableFeatures(array $features): void
    {
        $plan = Plan::factory()->create([
            'features' => array_merge(Plan::factory()->raw()['features'], $features),
        ]);

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'stripe_status' => 'active',
        ]);
    }
}
