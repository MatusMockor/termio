<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\StaffProfile;
use App\Models\WorkingHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_working_hours_returns_staff_schedule(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        WorkingHours::factory()
            ->forStaff($staff)
            ->forDay(1)
            ->hours('09:00', '17:00')
            ->create();

        WorkingHours::factory()
            ->forStaff($staff)
            ->forDay(2)
            ->hours('10:00', '18:00')
            ->create();

        $response = $this->getJson(route('staff.working-hours.index', $staff));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);
    }

    public function test_get_working_hours_returns_empty_for_new_staff(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        $response = $this->getJson(route('staff.working-hours.index', $staff));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_update_working_hours_creates_schedule(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        $data = [
            'working_hours' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'is_active' => true,
                ],
                [
                    'day_of_week' => 2,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'is_active' => true,
                ],
                [
                    'day_of_week' => 3,
                    'start_time' => '10:00',
                    'end_time' => '19:00',
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->putJson(route('staff.working-hours.update', $staff), $data);

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $this->assertDatabaseCount(WorkingHours::class, 3);

        $this->assertDatabaseHas(WorkingHours::class, [
            'staff_id' => $staff->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
    }

    public function test_update_working_hours_replaces_existing(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        WorkingHours::factory()
            ->forStaff($staff)
            ->forDay(1)
            ->hours('08:00', '16:00')
            ->create();

        WorkingHours::factory()
            ->forStaff($staff)
            ->forDay(2)
            ->hours('08:00', '16:00')
            ->create();

        $data = [
            'working_hours' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '10:00',
                    'end_time' => '20:00',
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->putJson(route('staff.working-hours.update', $staff), $data);

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseCount(WorkingHours::class, 1);

        $this->assertDatabaseHas(WorkingHours::class, [
            'staff_id' => $staff->id,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '20:00',
        ]);
    }

    public function test_update_working_hours_validates_time_format(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        $data = [
            'working_hours' => [
                [
                    'day_of_week' => 1,
                    'start_time' => 'invalid',
                    'end_time' => '18:00',
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->putJson(route('staff.working-hours.update', $staff), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['working_hours.0.start_time']);
    }

    public function test_update_working_hours_validates_end_after_start(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        $data = [
            'working_hours' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '18:00',
                    'end_time' => '09:00',
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->putJson(route('staff.working-hours.update', $staff), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['working_hours.0.end_time']);
    }

    public function test_update_working_hours_validates_day_of_week(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()
            ->forTenant($this->tenant)
            ->create();

        $data = [
            'working_hours' => [
                [
                    'day_of_week' => 7,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->putJson(route('staff.working-hours.update', $staff), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['working_hours.0.day_of_week']);
    }

    public function test_working_hours_requires_authentication(): void
    {
        $staff = StaffProfile::factory()->create();

        $response = $this->getJson(route('staff.working-hours.index', $staff));

        $response->assertUnauthorized();
    }
}
