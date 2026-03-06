<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\BookingField;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BookingFieldControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOwner();
        $this->enableFeatures(['custom_booking_fields' => true]);
    }

    public function test_owner_can_crud_fields_and_set_service_overrides(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();
        $fieldKey = fake()->unique()->regexify('[a-z]{6}_[a-z]{4}');
        $fieldLabel = fake()->words(2, true);
        $updatedFieldLabel = fake()->words(3, true);

        $storeResponse = $this->postJson(route('booking-fields.store'), [
            'key' => $fieldKey,
            'label' => $fieldLabel,
            'type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('data.key', $fieldKey);

        $fieldId = (int) $storeResponse->json('data.id');

        $updateResponse = $this->putJson(route('booking-fields.update', $fieldId), [
            'label' => $updatedFieldLabel,
            'is_required' => true,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.label', $updatedFieldLabel)
            ->assertJsonPath('data.is_required', true);

        $showResponse = $this->getJson(route('services.booking-fields.index', $service->id));

        $showResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', $fieldKey)
            ->assertJsonPath('data.0.base.is_required', true)
            ->assertJsonPath('data.0.override', null)
            ->assertJsonPath('data.0.effective.is_enabled', true);

        $overrideResponse = $this->putJson(route('services.booking-fields.update', $service->id), [
            'fields' => [
                [
                    'booking_field_id' => $fieldId,
                    'is_enabled' => true,
                    'is_required' => true,
                ],
            ],
        ]);

        $overrideResponse->assertOk()
            ->assertJsonPath('data.0.key', $fieldKey)
            ->assertJsonPath('data.0.override.is_enabled', true)
            ->assertJsonPath('data.0.override.is_required', true)
            ->assertJsonPath('data.0.effective.is_required', true);

        $this->assertDatabaseHas('service_booking_field_overrides', [
            'service_id' => $service->id,
            'booking_field_id' => $fieldId,
            'is_enabled' => true,
            'is_required' => true,
        ]);

        $deleteResponse = $this->deleteJson(route('booking-fields.destroy', $fieldId));

        $deleteResponse->assertNoContent();
        $this->assertSoftDeleted(BookingField::class, ['id' => $fieldId]);
    }

    public function test_type_options_validation_is_enforced(): void
    {
        $fieldKey = fake()->unique()->regexify('[a-z]{6}_[a-z]{4}');
        $fieldLabel = fake()->words(2, true);

        $response = $this->postJson(route('booking-fields.store'), [
            'key' => $fieldKey,
            'label' => $fieldLabel,
            'type' => 'text',
            'options' => [fake()->word(), fake()->word()],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    }

    public function test_tenant_isolation_blocks_cross_tenant_update(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherField = BookingField::factory()->forTenant($otherTenant)->create([
            'type' => 'text',
            'options' => null,
        ]);

        $response = $this->putJson(route('booking-fields.update', $otherField->id), [
            'label' => 'Cross Tenant',
        ]);

        $response->assertNotFound();
    }

    public function test_disabled_override_forces_effective_field_to_hidden_and_not_required(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();
        $field = BookingField::factory()->forTenant($this->tenant)->create([
            'type' => 'text',
            'options' => null,
            'is_required' => true,
        ]);

        $response = $this->putJson(route('services.booking-fields.update', $service->id), [
            'fields' => [
                [
                    'booking_field_id' => $field->id,
                    'is_enabled' => false,
                    'is_required' => true,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.override.is_enabled', false)
            ->assertJsonPath('data.0.override.is_required', false)
            ->assertJsonPath('data.0.effective.is_enabled', false)
            ->assertJsonPath('data.0.effective.is_required', false);

        $this->assertDatabaseHas('service_booking_field_overrides', [
            'service_id' => $service->id,
            'booking_field_id' => $field->id,
            'is_enabled' => false,
            'is_required' => false,
        ]);
    }

    /**
     * @param  array<string, bool>  $features
     */
    private function enableFeatures(array $features): void
    {
        $plan = Plan::factory()->create([
            'features' => $features,
        ]);

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'stripe_status' => 'active',
        ]);
    }
}
