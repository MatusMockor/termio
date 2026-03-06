<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Appointment;
use App\Models\BookingField;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServiceBookingFieldOverride;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicBookingCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $publicTenant;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::factory()->free()->create();
        $this->publicTenant = Tenant::factory()->create([
            'slug' => 'public-custom-fields',
        ]);

        $this->enableFeatures($this->publicTenant, [
            'custom_booking_fields' => true,
        ]);
    }

    public function test_required_custom_field_missing_returns_422(): void
    {
        $service = Service::factory()->forTenant($this->publicTenant)->create();
        $field = BookingField::factory()->forTenant($this->publicTenant)->create([
            'key' => 'allergy',
            'type' => 'text',
            'is_required' => false,
        ]);

        ServiceBookingFieldOverride::create([
            'service_id' => $service->id,
            'booking_field_id' => $field->id,
            'is_enabled' => true,
            'is_required' => true,
        ]);

        $response = $this->postJson(route('booking.create', ['tenantSlug' => $this->publicTenant->slug]), [
            'service_id' => $service->id,
            'starts_at' => Carbon::now()->addDays(2)->setHour(10)->toIso8601String(),
            'client_name' => 'Jane Doe',
            'client_phone' => '+421900000010',
            'client_email' => 'jane@example.com',
            'custom_fields' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_fields.allergy']);
    }

    public function test_wrong_type_for_custom_field_returns_422(): void
    {
        $service = Service::factory()->forTenant($this->publicTenant)->create();

        BookingField::factory()->forTenant($this->publicTenant)->create([
            'key' => 'age',
            'type' => 'number',
            'is_required' => true,
        ]);

        $response = $this->postJson(route('booking.create', ['tenantSlug' => $this->publicTenant->slug]), [
            'service_id' => $service->id,
            'starts_at' => Carbon::now()->addDays(2)->setHour(11)->toIso8601String(),
            'client_name' => 'Jane Doe',
            'client_phone' => '+421900000011',
            'client_email' => 'jane2@example.com',
            'custom_fields' => [
                'age' => 'not-a-number',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_fields.age']);
    }

    public function test_valid_custom_fields_are_saved_to_appointment(): void
    {
        $service = Service::factory()->forTenant($this->publicTenant)->create();

        BookingField::factory()->forTenant($this->publicTenant)->create([
            'key' => 'allergy',
            'type' => 'text',
            'is_required' => true,
        ]);

        $response = $this->postJson(route('booking.create', ['tenantSlug' => $this->publicTenant->slug]), [
            'service_id' => $service->id,
            'starts_at' => Carbon::now()->addDays(2)->setHour(12)->toIso8601String(),
            'client_name' => 'Jane Doe',
            'client_phone' => '+421900000012',
            'client_email' => 'jane3@example.com',
            'custom_fields' => [
                'allergy' => 'No perfume',
            ],
        ]);

        $response->assertCreated();

        $appointmentId = (int) $response->json('appointment_id');
        $appointment = Appointment::findOrFail($appointmentId);

        $this->assertSame('No perfume', $appointment->custom_fields['allergy'] ?? null);
    }

    public function test_public_booking_fields_endpoint_returns_only_enabled_effective_fields(): void
    {
        $service = Service::factory()->forTenant($this->publicTenant)->create();
        $visibleField = BookingField::factory()->forTenant($this->publicTenant)->create([
            'key' => 'allergy',
            'type' => 'text',
            'options' => null,
            'is_active' => true,
        ]);
        $hiddenField = BookingField::factory()->forTenant($this->publicTenant)->create([
            'key' => 'internal_note',
            'type' => 'textarea',
            'options' => null,
            'is_active' => true,
        ]);

        ServiceBookingFieldOverride::create([
            'service_id' => $service->id,
            'booking_field_id' => $visibleField->id,
            'is_enabled' => true,
            'is_required' => true,
        ]);

        ServiceBookingFieldOverride::create([
            'service_id' => $service->id,
            'booking_field_id' => $hiddenField->id,
            'is_enabled' => false,
            'is_required' => true,
        ]);

        $response = $this->getJson(route('booking.services.booking-fields', [
            'tenantSlug' => $this->publicTenant->slug,
            'serviceId' => $service->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'allergy')
            ->assertJsonPath('data.0.is_required', true);
    }

    public function test_custom_fields_are_rejected_when_feature_is_disabled(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'public-custom-fields-disabled',
        ]);
        $service = Service::factory()->forTenant($tenant)->create();
        BookingField::factory()->forTenant($tenant)->create([
            'key' => 'allergy',
            'type' => 'text',
            'options' => null,
            'is_required' => true,
        ]);

        $response = $this->postJson(route('booking.create', ['tenantSlug' => $tenant->slug]), [
            'service_id' => $service->id,
            'starts_at' => Carbon::now()->addDays(2)->setHour(13)->toIso8601String(),
            'client_name' => 'Jane Doe',
            'client_phone' => '+421900000013',
            'client_email' => 'jane4@example.com',
            'custom_fields' => [
                'allergy' => 'No perfume',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_fields']);
    }

    public function test_invalid_date_custom_field_returns_422(): void
    {
        $service = Service::factory()->forTenant($this->publicTenant)->create();

        BookingField::factory()->forTenant($this->publicTenant)->create([
            'key' => 'visit_date',
            'type' => 'date',
            'options' => null,
            'is_required' => true,
        ]);

        $response = $this->postJson(route('booking.create', ['tenantSlug' => $this->publicTenant->slug]), [
            'service_id' => $service->id,
            'starts_at' => Carbon::now()->addDays(2)->setHour(14)->toIso8601String(),
            'client_name' => 'Jane Doe',
            'client_phone' => '+421900000014',
            'client_email' => 'jane5@example.com',
            'custom_fields' => [
                'visit_date' => '2026/03/10',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_fields.visit_date']);
    }

    /**
     * @param  array<string, bool>  $features
     */
    private function enableFeatures(Tenant $tenant, array $features): void
    {
        $plan = Plan::factory()->create([
            'name' => 'PLAN_'.strtoupper(fake()->unique()->lexify('??????')),
            'slug' => fake()->unique()->slug(),
            'features' => array_merge(Plan::factory()->raw()['features'], $features),
        ]);

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'stripe_status' => 'active',
        ]);
    }
}
