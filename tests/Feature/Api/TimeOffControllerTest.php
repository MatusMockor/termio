<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\StaffProfile;
use App\Models\TimeOff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TimeOffControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_time_off_list(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        TimeOff::factory()
            ->forStaff($staff)
            ->forDate(Carbon::tomorrow()->toDateString())
            ->allDay()
            ->create();

        TimeOff::factory()
            ->forTenant($this->tenant)
            ->forDate(Carbon::tomorrow()->addDays(2)->toDateString())
            ->allDay()
            ->create();

        $response = $this->getJson(route('time-off.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_staff_id(): void
    {
        $this->actingAsOwner();

        $staff1 = StaffProfile::factory()->forTenant($this->tenant)->create();
        $staff2 = StaffProfile::factory()->forTenant($this->tenant)->create();

        TimeOff::factory()->forStaff($staff1)->forDate(Carbon::tomorrow()->toDateString())->create();
        TimeOff::factory()->forStaff($staff2)->forDate(Carbon::tomorrow()->toDateString())->create();

        $response = $this->getJson(route('time-off.index', ['staff_id' => $staff1->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_date_range(): void
    {
        $this->actingAsOwner();

        TimeOff::factory()
            ->forTenant($this->tenant)
            ->forDate(Carbon::tomorrow()->toDateString())
            ->create();

        TimeOff::factory()
            ->forTenant($this->tenant)
            ->forDate(Carbon::tomorrow()->addDays(10)->toDateString())
            ->create();

        $response = $this->getJson(route('time-off.index', [
            'start_date' => Carbon::tomorrow()->toDateString(),
            'end_date' => Carbon::tomorrow()->addDays(5)->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_creates_all_day_time_off(): void
    {
        $this->actingAsOwner();

        $date = Carbon::tomorrow()->toDateString();
        $reason = fake()->sentence();

        $response = $this->postJson(route('time-off.store'), [
            'date' => $date,
            'reason' => $reason,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.reason', $reason)
            ->assertJsonPath('data.start_time', null)
            ->assertJsonPath('data.end_time', null);

        $this->assertStringContainsString($date, $response->json('data.date'));

        $this->assertDatabaseHas(TimeOff::class, [
            'tenant_id' => $this->tenant->id,
            'reason' => $reason,
        ]);
    }

    public function test_store_creates_partial_time_off(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        $date = Carbon::tomorrow()->toDateString();

        $response = $this->postJson(route('time-off.store'), [
            'staff_id' => $staff->id,
            'date' => $date,
            'start_time' => '12:00',
            'end_time' => '14:00',
            'reason' => 'Lunch break',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.staff_id', $staff->id)
            ->assertJsonPath('data.start_time', '12:00')
            ->assertJsonPath('data.end_time', '14:00');

        $this->assertDatabaseHas(TimeOff::class, [
            'staff_id' => $staff->id,
            'start_time' => '12:00',
            'end_time' => '14:00',
        ]);
    }

    public function test_store_validates_date_is_required(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('time-off.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_store_validates_date_is_in_future(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('time-off.store'), [
            'date' => Carbon::yesterday()->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_store_validates_end_time_after_start_time(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('time-off.store'), [
            'date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '12:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_show_returns_time_off(): void
    {
        $this->actingAsOwner();

        $timeOff = TimeOff::factory()
            ->forTenant($this->tenant)
            ->forDate(Carbon::tomorrow()->toDateString())
            ->withReason('Holiday')
            ->create();

        $response = $this->getJson(route('time-off.show', $timeOff));

        $response->assertOk()
            ->assertJsonPath('data.id', $timeOff->id)
            ->assertJsonPath('data.reason', 'Holiday');
    }

    public function test_update_modifies_time_off(): void
    {
        $this->actingAsOwner();

        $timeOff = TimeOff::factory()
            ->forTenant($this->tenant)
            ->forDate(Carbon::tomorrow()->toDateString())
            ->create();

        $newReason = fake()->sentence();

        $response = $this->putJson(route('time-off.update', $timeOff), [
            'reason' => $newReason,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reason', $newReason)
            ->assertJsonPath('data.start_time', '09:00')
            ->assertJsonPath('data.end_time', '12:00');
    }

    public function test_destroy_deletes_time_off(): void
    {
        $this->actingAsOwner();

        $timeOff = TimeOff::factory()
            ->forTenant($this->tenant)
            ->forDate(Carbon::tomorrow()->toDateString())
            ->create();

        $response = $this->deleteJson(route('time-off.destroy', $timeOff));

        $response->assertNoContent();

        $this->assertDatabaseMissing(TimeOff::class, [
            'id' => $timeOff->id,
        ]);
    }

    public function test_endpoints_require_authentication(): void
    {
        $timeOff = TimeOff::factory()->create();

        $this->getJson(route('time-off.index'))->assertUnauthorized();
        $this->postJson(route('time-off.store'))->assertUnauthorized();
        $this->getJson(route('time-off.show', $timeOff))->assertUnauthorized();
        $this->putJson(route('time-off.update', $timeOff))->assertUnauthorized();
        $this->deleteJson(route('time-off.destroy', $timeOff))->assertUnauthorized();
    }
}
