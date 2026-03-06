<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WaitlistControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOwner();
        $this->enableFeatures([
            'waitlist_management' => true,
            'reservation_replacements' => true,
        ]);
    }

    public function test_waitlist_create_list_update_and_convert_flow(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();
        $staff = StaffProfile::factory()->forTenant($this->tenant)->bookable()->create();
        $staff->services()->attach($service->id);
        $preferredDate = Carbon::tomorrow();

        WorkingHours::factory()->forTenant($this->tenant)->create([
            'staff_id' => $staff->id,
            'day_of_week' => $preferredDate->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $storeResponse = $this->postJson(route('waitlist.store'), [
            'service_id' => $service->id,
            'preferred_staff_id' => $staff->id,
            'client_name' => 'John Doe',
            'client_phone' => '+421900000001',
            'client_email' => 'john@example.com',
            'preferred_date' => $preferredDate->toDateString(),
            'time_from' => '10:00',
            'time_to' => '12:00',
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('data.client_name', 'John Doe')
            ->assertJsonPath('data.service.id', $service->id)
            ->assertJsonPath('data.preferred_staff.id', $staff->id);

        $entryId = (int) $storeResponse->json('data.id');

        $listResponse = $this->getJson(route('waitlist.index', [
            'status' => 'pending',
            'service_id' => $service->id,
            'preferred_staff_id' => $staff->id,
            'preferred_date' => $preferredDate->toDateString(),
            'search' => 'John',
            'per_page' => '1',
        ]));

        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.service.name', $service->name)
            ->assertJsonPath('data.0.preferred_staff.name', $staff->display_name);

        $updateResponse = $this->patchJson(route('waitlist.update', $entryId), [
            'status' => 'contacted',
            'notes' => 'Called customer back',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.status', 'contacted')
            ->assertJsonPath('data.notes', 'Called customer back');

        $startsAt = $preferredDate->copy()->setHour(10)->setMinute(0);

        $convertResponse = $this->postJson(route('waitlist.convert', $entryId), [
            'starts_at' => $startsAt->toIso8601String(),
            'staff_id' => $staff->id,
            'notes' => 'Replacement appointment',
        ]);

        $convertResponse->assertCreated()
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.status', 'confirmed');

        $entry = WaitlistEntry::findOrFail($entryId);
        $this->assertSame('converted', $entry->status->value);
        $this->assertNotNull($entry->converted_appointment_id);

        $reupdateResponse = $this->patchJson(route('waitlist.update', $entryId), [
            'notes' => 'Cannot edit anymore',
        ]);

        $reupdateResponse->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_replacement_candidates_and_replace_from_waitlist(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();
        $staff = StaffProfile::factory()->forTenant($this->tenant)->bookable()->create();
        $staff->services()->attach($service->id);
        $client = Client::factory()->forTenant($this->tenant)->create();
        $targetDate = Carbon::tomorrow();

        WorkingHours::factory()->forTenant($this->tenant)->create([
            'staff_id' => $staff->id,
            'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $cancelledAppointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->cancelled()
            ->create([
                'starts_at' => $targetDate->copy()->setHour(11),
                'ends_at' => $targetDate->copy()->setHour(12),
            ]);

        $entry = WaitlistEntry::factory()->forTenant($this->tenant)->create([
            'service_id' => $service->id,
            'preferred_staff_id' => $staff->id,
            'status' => 'pending',
            'preferred_date' => $targetDate->toDateString(),
        ]);

        $candidatesResponse = $this->getJson(route('appointments.replacement-candidates', $cancelledAppointment));

        $candidatesResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id)
            ->assertJsonPath('data.0.match.matched_by', 'strict')
            ->assertJsonPath('data.0.match.matches_preferred_date', true)
            ->assertJsonPath('data.0.match.matches_preferred_staff', true);

        $replaceResponse = $this->postJson(route('appointments.replace-from-waitlist', $cancelledAppointment), [
            'waitlist_entry_id' => $entry->id,
        ]);

        $replaceResponse->assertCreated()
            ->assertJsonPath('data.service_id', $service->id);

        $this->assertSame('converted', $entry->fresh()->status->value);
    }

    public function test_update_rejects_staff_that_does_not_provide_the_selected_service(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();
        $entry = WaitlistEntry::factory()->forTenant($this->tenant)->create([
            'service_id' => $service->id,
            'preferred_staff_id' => null,
            'status' => 'pending',
        ]);
        $invalidStaff = StaffProfile::factory()->forTenant($this->tenant)->bookable()->create();

        $response = $this->patchJson(route('waitlist.update', $entry), [
            'preferred_staff_id' => $invalidStaff->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['preferred_staff_id']);
    }

    public function test_convert_rejects_unavailable_slot(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();
        $staff = StaffProfile::factory()->forTenant($this->tenant)->bookable()->create();
        $staff->services()->attach($service->id);
        $client = Client::factory()->forTenant($this->tenant)->create();
        $targetDate = Carbon::tomorrow();

        WorkingHours::factory()->forTenant($this->tenant)->create([
            'staff_id' => $staff->id,
            'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->confirmed()
            ->create([
                'starts_at' => $targetDate->copy()->setHour(10),
                'ends_at' => $targetDate->copy()->setHour(11),
            ]);

        $entry = WaitlistEntry::factory()->forTenant($this->tenant)->create([
            'service_id' => $service->id,
            'preferred_staff_id' => $staff->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson(route('waitlist.convert', $entry), [
            'starts_at' => $targetDate->copy()->setHour(10)->toIso8601String(),
            'staff_id' => $staff->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_cross_tenant_convert_is_blocked(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherEntry = WaitlistEntry::factory()->forTenant($otherTenant)->create();

        $response = $this->postJson(route('waitlist.convert', $otherEntry->id), [
            'starts_at' => Carbon::tomorrow()->toIso8601String(),
        ]);

        $response->assertNotFound();
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
