<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'test-salon']);
    }

    public function test_tenant_info_returns_tenant_details(): void
    {
        $response = $this->getJson(route('booking.info', ['tenantSlug' => $this->tenant->slug]));

        $response->assertOk()
            ->assertJsonStructure(['name', 'business_type', 'address', 'phone'])
            ->assertJsonPath('name', $this->tenant->name);
    }

    public function test_tenant_info_returns_404_for_invalid_slug(): void
    {
        $response = $this->getJson(route('booking.info', ['tenantSlug' => 'non-existent']));

        $response->assertNotFound();
    }

    public function test_services_returns_active_bookable_services(): void
    {
        Service::factory()->forTenant($this->tenant)->count(2)->create();
        Service::factory()->forTenant($this->tenant)->inactive()->create();
        Service::factory()->forTenant($this->tenant)->notBookableOnline()->create();

        $response = $this->getJson(route('booking.services', ['tenantSlug' => $this->tenant->slug]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'duration_minutes', 'price'],
                ],
            ]);
    }

    public function test_staff_returns_bookable_staff(): void
    {
        StaffProfile::factory()->forTenant($this->tenant)->bookable()->count(2)->create();
        StaffProfile::factory()->forTenant($this->tenant)->notBookable()->create();

        $response = $this->getJson(route('booking.staff', ['tenantSlug' => $this->tenant->slug]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'display_name', 'bio', 'photo_url'],
                ],
            ]);
    }

    public function test_staff_services_returns_services_for_staff(): void
    {
        $staff = StaffProfile::factory()->forTenant($this->tenant)->bookable()->create();
        $service1 = Service::factory()->forTenant($this->tenant)->create();
        $service2 = Service::factory()->forTenant($this->tenant)->create();

        $staff->services()->attach([$service1->id, $service2->id]);

        $response = $this->getJson(route('booking.staff.services', [
            'tenantSlug' => $this->tenant->slug,
            'staffId' => $staff->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_availability_returns_time_slots(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create(['duration_minutes' => 60]);

        $targetDate = Carbon::tomorrow();
        $dayOfWeek = $targetDate->dayOfWeek;

        WorkingHours::factory()->forTenant($this->tenant)->create([
            'staff_id' => null,
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $response = $this->getJson(route('booking.availability', [
            'tenantSlug' => $this->tenant->slug,
            'service_id' => $service->id,
            'date' => $targetDate->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'slots' => [
                    '*' => ['time', 'available'],
                ],
            ]);

        $slots = $response->json('slots');
        $this->assertNotEmpty($slots);
    }

    public function test_availability_excludes_booked_slots(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create(['duration_minutes' => 30]);
        $client = Client::factory()->forTenant($this->tenant)->create();

        $targetDate = Carbon::tomorrow();
        $dayOfWeek = $targetDate->dayOfWeek;

        WorkingHours::factory()->forTenant($this->tenant)->create([
            'staff_id' => null,
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->at($targetDate->copy()->setHour(10)->setMinute(0))
            ->confirmed()
            ->create();

        $response = $this->getJson(route('booking.availability', [
            'tenantSlug' => $this->tenant->slug,
            'service_id' => $service->id,
            'date' => $targetDate->toDateString(),
        ]));

        $response->assertOk();

        $slots = collect($response->json('slots'));
        $slot1000 = $slots->firstWhere('time', '10:00');

        $this->assertFalse($slot1000['available']);
    }

    public function test_availability_returns_empty_for_non_working_day(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();

        $targetDate = Carbon::tomorrow();

        $response = $this->getJson(route('booking.availability', [
            'tenantSlug' => $this->tenant->slug,
            'service_id' => $service->id,
            'date' => $targetDate->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_availability_validates_required_fields(): void
    {
        $response = $this->getJson(route('booking.availability', [
            'tenantSlug' => $this->tenant->slug,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id', 'date']);
    }

    public function test_create_booking_creates_appointment_and_client(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create(['duration_minutes' => 60]);

        $startsAt = Carbon::tomorrow()->setHour(10)->setMinute(0);
        $clientName = fake()->name();
        $clientPhone = fake()->phoneNumber();
        $clientEmail = fake()->safeEmail();

        $response = $this->postJson(route('booking.create', ['tenantSlug' => $this->tenant->slug]), [
            'service_id' => $service->id,
            'starts_at' => $startsAt->toIso8601String(),
            'client_name' => $clientName,
            'client_phone' => $clientPhone,
            'client_email' => $clientEmail,
            'notes' => fake()->sentence(),
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'appointment' => ['id', 'service', 'starts_at', 'ends_at'],
            ]);

        $this->assertDatabaseHas(Client::class, [
            'tenant_id' => $this->tenant->id,
            'phone' => $clientPhone,
        ]);

        $this->assertDatabaseHas(Appointment::class, [
            'tenant_id' => $this->tenant->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'source' => 'online',
        ]);
    }

    public function test_create_booking_uses_existing_client(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();
        $existingClient = Client::factory()->forTenant($this->tenant)->create();

        $startsAt = Carbon::tomorrow()->setHour(10)->setMinute(0);

        $response = $this->postJson(route('booking.create', ['tenantSlug' => $this->tenant->slug]), [
            'service_id' => $service->id,
            'starts_at' => $startsAt->toIso8601String(),
            'client_name' => fake()->name(),
            'client_phone' => $existingClient->phone,
        ]);

        $response->assertCreated();

        $this->assertEquals(1, Client::where('phone', $existingClient->phone)->count());
    }

    public function test_create_booking_validates_required_fields(): void
    {
        $response = $this->postJson(route('booking.create', ['tenantSlug' => $this->tenant->slug]), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id', 'starts_at', 'client_name', 'client_phone']);
    }

    public function test_create_booking_validates_future_date(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();

        $response = $this->postJson(route('booking.create', ['tenantSlug' => $this->tenant->slug]), [
            'service_id' => $service->id,
            'starts_at' => Carbon::yesterday()->toIso8601String(),
            'client_name' => fake()->name(),
            'client_phone' => fake()->phoneNumber(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    }
}
