<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_appointments_list(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create();

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->count(3)
            ->create();

        $response = $this->getJson(route('appointments.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'starts_at', 'ends_at', 'status', 'client', 'service'],
                ],
            ]);
    }

    public function test_index_filters_by_date(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create();
        $targetDate = Carbon::tomorrow();

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->at($targetDate->copy()->setHour(10))
            ->create();

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->at(Carbon::tomorrow()->addDays(5)->setHour(10))
            ->create();

        $response = $this->getJson(route('appointments.index', ['date' => $targetDate->toDateString()]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create();

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->confirmed()
            ->create();

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->cancelled()
            ->create();

        $response = $this->getJson(route('appointments.index', ['status' => 'confirmed']));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_validates_filters(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson(route('appointments.index', [
            'status' => 'invalid-status',
            'start_date' => '2026-02-25',
            'end_date' => '2026-02-24',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'end_date']);
    }

    public function test_index_supports_pagination(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create();

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->count(3)
            ->create();

        $response = $this->getJson(route('appointments.index', ['per_page' => 2]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_calendar_returns_limited_appointments_per_day_with_pagination_meta(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create(['duration_minutes' => 30]);
        $targetDate = Carbon::tomorrow()->startOfDay()->setHour(8);
        $perDay = (int) config('appointments.calendar.per_day.default');
        $totalAppointments = $perDay + 2;

        for ($index = 0; $index < $totalAppointments; $index++) {
            Appointment::factory()
                ->forTenant($this->tenant)
                ->forClient($client)
                ->forService($service)
                ->at($targetDate->copy()->addMinutes($index * 30))
                ->create();
        }

        $response = $this->getJson(route('appointments.calendar', [
            'start_date' => $targetDate->toDateString(),
            'end_date' => $targetDate->toDateString(),
            'per_day' => $perDay,
        ]));

        $response->assertOk();

        $dateKey = $targetDate->toDateString();
        /** @var array<string, mixed> $payload */
        $payload = $response->json('data');
        /** @var array<string, mixed> $dayPayload */
        $dayPayload = $payload['days'][$dateKey];

        $this->assertCount($perDay, $dayPayload['appointments']);
        $this->assertSame($totalAppointments, $dayPayload['pagination']['total']);
        $this->assertTrue($dayPayload['pagination']['has_more']);
        $this->assertSame($perDay, $dayPayload['pagination']['next_offset']);
    }

    public function test_calendar_day_returns_next_batch_for_specific_day(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create(['duration_minutes' => 30]);
        $targetDate = Carbon::tomorrow()->startOfDay()->setHour(8);
        $limit = (int) config('appointments.calendar.per_day.default');
        $offset = $limit;
        $totalAppointments = ($limit * 2) + 1;

        for ($index = 0; $index < $totalAppointments; $index++) {
            Appointment::factory()
                ->forTenant($this->tenant)
                ->forClient($client)
                ->forService($service)
                ->at($targetDate->copy()->addMinutes($index * 30))
                ->create();
        }

        $firstResponse = $this->getJson(route('appointments.calendar.day', [
            'date' => $targetDate->toDateString(),
            'offset' => $offset,
            'limit' => $limit,
        ]));

        $firstResponse->assertOk();
        $firstPayload = $firstResponse->json('data');

        $this->assertCount($limit, $firstPayload['appointments']);
        $this->assertSame($totalAppointments, $firstPayload['pagination']['total']);
        $this->assertTrue($firstPayload['pagination']['has_more']);
        $this->assertSame($offset + $limit, $firstPayload['pagination']['next_offset']);

        $secondResponse = $this->getJson(route('appointments.calendar.day', [
            'date' => $targetDate->toDateString(),
            'offset' => $offset + $limit,
            'limit' => $limit,
        ]));

        $secondResponse->assertOk();
        $secondPayload = $secondResponse->json('data');

        $remaining = $totalAppointments - ($offset + $limit);

        $this->assertCount($remaining, $secondPayload['appointments']);
        $this->assertFalse($secondPayload['pagination']['has_more']);
        $this->assertSame($totalAppointments, $secondPayload['pagination']['next_offset']);
    }

    public function test_store_creates_appointment(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create(['duration_minutes' => 60]);
        $startsAt = Carbon::tomorrow()->setHour(10)->setMinute(0);

        $response = $this->postJson(route('appointments.store'), [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt->toIso8601String(),
            'notes' => fake()->sentence(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas(Appointment::class, [
            'client_id' => $client->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_store_creates_appointment_with_calendar_date_format(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $durationMinutes = fake()->randomElement([30, 45, 60, 75, 90]);
        $service = Service::factory()->forTenant($this->tenant)->create(['duration_minutes' => $durationMinutes]);
        $tomorrow = fake()->dateTimeBetween('tomorrow', 'tomorrow');
        $hour = fake()->numberBetween(8, 16);
        $startsAt = Carbon::instance($tomorrow)->format('Y-m-d').sprintf('T%02d:00', $hour);

        $response = $this->postJson(route('appointments.store'), [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);

        $appointment = Appointment::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertEquals($hour, $appointment->starts_at->hour);
        $this->assertEquals($durationMinutes, $appointment->starts_at->diffInMinutes($appointment->ends_at));
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('appointments.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id', 'service_id', 'starts_at']);
    }

    public function test_show_returns_appointment(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create();

        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->create();

        $response = $this->getJson(route('appointments.show', $appointment));

        $response->assertOk()
            ->assertJsonPath('data.id', $appointment->id)
            ->assertJsonStructure([
                'data' => ['id', 'starts_at', 'ends_at', 'status', 'client', 'service'],
            ]);
    }

    public function test_update_modifies_appointment(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create();

        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->create(['status' => 'pending']);

        $newNotes = fake()->sentence();

        $response = $this->putJson(route('appointments.update', $appointment), [
            'notes' => $newNotes,
            'status' => 'confirmed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.notes', $newNotes)
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_destroy_deletes_appointment(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create();

        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->create();

        $response = $this->deleteJson(route('appointments.destroy', $appointment));

        $response->assertNoContent();

        $this->assertSoftDeleted(Appointment::class, [
            'id' => $appointment->id,
        ]);
    }

    public function test_complete_marks_appointment_as_completed(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create(['total_visits' => 0, 'total_spent' => 0]);
        $service = Service::factory()->forTenant($this->tenant)->create(['price' => 50.00]);

        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->confirmed()
            ->create();

        $response = $this->postJson(route('appointments.complete', $appointment));

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $client->refresh();
        $this->assertEquals(1, $client->total_visits);
        $this->assertEquals(50.00, $client->total_spent);
    }

    public function test_cancel_marks_appointment_as_cancelled(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create();

        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->confirmed()
            ->create();

        $reason = fake()->sentence();

        $response = $this->postJson(route('appointments.cancel', $appointment), [
            'reason' => $reason,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $appointment->refresh();
        $this->assertStringContainsString($reason, $appointment->notes);
    }

    public function test_endpoints_require_authentication(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $client = Client::factory()->forTenant($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create();
        $appointment = Appointment::factory()->forTenant($tenant)->forClient($client)->forService($service)->create();

        $this->getJson(route('appointments.index'))->assertUnauthorized();
        $this->postJson(route('appointments.store'))->assertUnauthorized();
        $this->getJson(route('appointments.show', $appointment))->assertUnauthorized();
        $this->putJson(route('appointments.update', $appointment))->assertUnauthorized();
        $this->deleteJson(route('appointments.destroy', $appointment))->assertUnauthorized();
        $this->postJson(route('appointments.complete', $appointment))->assertUnauthorized();
        $this->postJson(route('appointments.cancel', $appointment))->assertUnauthorized();
    }
}
