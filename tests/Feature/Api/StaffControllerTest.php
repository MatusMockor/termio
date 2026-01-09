<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StaffControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_staff_list(): void
    {
        $this->actingAsOwner();

        StaffProfile::factory()
            ->forTenant($this->tenant)
            ->count(3)
            ->create();

        $response = $this->getJson(route('staff.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'display_name',
                        'bio',
                        'photo_url',
                        'specializations',
                        'is_bookable',
                        'sort_order',
                    ],
                ],
            ]);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson(route('staff.index'));

        $response->assertUnauthorized();
    }

    public function test_store_creates_staff(): void
    {
        $this->actingAsOwner();

        $displayName = fake()->name();
        $bio = fake()->sentence();
        $specializations = fake()->randomElements(['Fade', 'Beard trim', 'Classic cut', 'Coloring'], 2);

        $response = $this->postJson(route('staff.store'), [
            'display_name' => $displayName,
            'bio' => $bio,
            'specializations' => $specializations,
            'is_bookable' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.display_name', $displayName)
            ->assertJsonPath('data.bio', $bio)
            ->assertJsonPath('data.specializations', $specializations);

        $this->assertDatabaseHas(StaffProfile::class, [
            'tenant_id' => $this->tenant->id,
            'display_name' => $displayName,
        ]);
    }

    public function test_store_creates_staff_with_services(): void
    {
        $this->actingAsOwner();

        $services = Service::factory()
            ->forTenant($this->tenant)
            ->count(2)
            ->create();

        $displayName = fake()->name();

        $response = $this->postJson(route('staff.store'), [
            'display_name' => $displayName,
            'service_ids' => $services->pluck('id')->toArray(),
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.services');

        $staffId = $response->json('data.id');
        $staff = StaffProfile::find($staffId);

        $this->assertCount(2, $staff->services);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('staff.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['display_name']);
    }

    public function test_show_returns_staff_member(): void
    {
        $this->actingAsOwner();

        $displayName = fake()->name();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create(['display_name' => $displayName]);

        $response = $this->getJson(route('staff.show', $staff));

        $response->assertOk()
            ->assertJsonPath('data.display_name', $displayName);
    }

    public function test_update_modifies_staff(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        $newName = fake()->name();
        $newBio = fake()->sentence();

        $response = $this->putJson(route('staff.update', $staff), [
            'display_name' => $newName,
            'bio' => $newBio,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.display_name', $newName)
            ->assertJsonPath('data.bio', $newBio);
    }

    public function test_update_syncs_services(): void
    {
        $this->actingAsOwner();

        $services = Service::factory()
            ->forTenant($this->tenant)
            ->count(3)
            ->create();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        $staff->services()->attach($services->take(2)->pluck('id'));

        $response = $this->putJson(route('staff.update', $staff), [
            'service_ids' => [$services->last()->id],
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.services');
    }

    public function test_destroy_deletes_staff(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        $response = $this->deleteJson(route('staff.destroy', $staff));

        $response->assertNoContent();

        $this->assertDatabaseMissing(StaffProfile::class, [
            'id' => $staff->id,
        ]);
    }

    public function test_reorder_updates_sort_order(): void
    {
        $this->actingAsOwner();

        $staff1 = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create(['sort_order' => 0]);

        $staff2 = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create(['sort_order' => 1]);

        $staff3 = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create(['sort_order' => 2]);

        $response = $this->postJson(route('staff.reorder'), [
            'order' => [$staff3->id, $staff1->id, $staff2->id],
        ]);

        $response->assertOk();

        $this->assertEquals(0, $staff3->fresh()->sort_order);
        $this->assertEquals(1, $staff1->fresh()->sort_order);
        $this->assertEquals(2, $staff2->fresh()->sort_order);
    }
}
