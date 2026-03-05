<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
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

    public function test_waitlist_create_list_and_convert_flow(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();

        $storeResponse = $this->postJson(route('waitlist.store'), [
            'service_id' => $service->id,
            'client_name' => 'John Doe',
            'client_phone' => '+421900000001',
            'client_email' => 'john@example.com',
            'preferred_date' => Carbon::tomorrow()->toDateString(),
            'time_from' => '10:00',
            'time_to' => '12:00',
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('data.client_name', 'John Doe');

        $entryId = (int) $storeResponse->json('data.id');

        $listResponse = $this->getJson(route('waitlist.index'));

        $listResponse->assertOk()
            ->assertJsonCount(1, 'data');

        $startsAt = Carbon::tomorrow()->setHour(10)->setMinute(0);

        $convertResponse = $this->postJson(route('waitlist.convert', $entryId), [
            'starts_at' => $startsAt->toIso8601String(),
            'notes' => 'Replacement appointment',
        ]);

        $convertResponse->assertCreated()
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.status', 'confirmed');

        $entry = WaitlistEntry::findOrFail($entryId);
        $this->assertSame('converted', $entry->status->value);
        $this->assertNotNull($entry->converted_appointment_id);
    }

    public function test_replacement_candidates_and_replace_from_waitlist(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create();
        $client = Client::factory()->forTenant($this->tenant)->create();

        $cancelledAppointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->cancelled()
            ->create([
                'starts_at' => Carbon::tomorrow()->setHour(11),
                'ends_at' => Carbon::tomorrow()->setHour(12),
            ]);

        $entry = WaitlistEntry::factory()->forTenant($this->tenant)->create([
            'service_id' => $service->id,
            'status' => 'pending',
            'preferred_date' => Carbon::tomorrow()->toDateString(),
        ]);

        $candidatesResponse = $this->getJson(route('appointments.replacement-candidates', $cancelledAppointment));

        $candidatesResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);

        $replaceResponse = $this->postJson(route('appointments.replace-from-waitlist', $cancelledAppointment), [
            'waitlist_entry_id' => $entry->id,
        ]);

        $replaceResponse->assertCreated()
            ->assertJsonPath('data.service_id', $service->id);

        $this->assertSame('converted', $entry->fresh()->status->value);
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
