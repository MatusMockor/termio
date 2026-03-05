<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Appointment;
use App\Models\BookingField;
use App\Models\Service;
use App\Models\ServiceBookingFieldOverride;
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

        $this->publicTenant = Tenant::factory()->create([
            'slug' => 'public-custom-fields',
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
}
