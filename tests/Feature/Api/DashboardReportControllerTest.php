<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_returns_full_metrics(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create(['price' => 50.00]);
        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();

        $today = Carbon::today();

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->completed()
            ->count(3)
            ->create();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'today',
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period' => ['start', 'end'],
                    'metrics' => [
                        'total_revenue',
                        'total',
                        'completed',
                        'cancelled',
                        'no_show',
                        'occupancy_rate',
                        'no_show_rate',
                        'cancellation_rate',
                        'new_clients',
                        'returning_clients',
                        'new_client_ratio',
                    ],
                    'top_staff_by_revenue',
                    'top_staff_by_appointments',
                    'revenue_by_service',
                    'comparison',
                ],
            ]);

        $this->assertEquals(3, $response->json('data.metrics.total'));
        $this->assertEquals(150.00, $response->json('data.metrics.total_revenue'));
    }

    public function test_report_accepts_this_week_period(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'this_week',
        ]));

        $response->assertOk();

        $startDate = Carbon::parse($response->json('data.period.start'));
        $endDate = Carbon::parse($response->json('data.period.end'));

        $this->assertTrue($startDate->isStartOfWeek());
        $this->assertTrue($endDate->isEndOfWeek());
    }

    public function test_report_accepts_this_month_period(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'this_month',
        ]));

        $response->assertOk();

        $startDate = Carbon::parse($response->json('data.period.start'));
        $endDate = Carbon::parse($response->json('data.period.end'));

        $this->assertTrue($startDate->isStartOfMonth());
        $this->assertTrue($endDate->isEndOfMonth());
    }

    public function test_report_accepts_custom_period_with_dates(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-15',
        ]));

        $response->assertOk();

        $this->assertEquals('2026-01-01', $response->json('data.period.start'));
        $this->assertEquals('2026-01-15', $response->json('data.period.end'));
    }

    public function test_report_validates_custom_period_requires_dates(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'custom',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_report_can_exclude_comparison(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'today',
            'include_comparison' => false,
        ]));

        $response->assertOk();
        $this->assertNull($response->json('data.comparison'));
    }

    public function test_report_includes_comparison_by_default(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $this->assertNotNull($response->json('data.comparison'));
    }

    public function test_report_validates_period_format(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'invalid_period',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);
    }

    public function test_report_requires_authentication(): void
    {
        $response = $this->getJson(route('dashboard.report', [
            'period' => 'today',
        ]));

        $response->assertUnauthorized();
    }

    public function test_report_shows_correct_revenue_by_service(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();

        $service1 = Service::factory()->forTenant($this->tenant)->create([
            'name' => 'Strihanie',
            'price' => 20.00,
        ]);

        $service2 = Service::factory()->forTenant($this->tenant)->create([
            'name' => 'Farbenie',
            'price' => 80.00,
        ]);

        $today = Carbon::today();

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service1)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->completed()
            ->count(3)
            ->create();

        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service2)
            ->forStaff($staff)
            ->at($today->copy()->setHour(11))
            ->completed()
            ->count(2)
            ->create();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'today',
        ]));

        $response->assertOk();

        $revenueByService = $response->json('data.revenue_by_service');

        $this->assertCount(2, $revenueByService);

        // Farbenie should be first (160 > 60)
        $this->assertEquals('Farbenie', $revenueByService[0]['service_name']);
        $this->assertEquals(160.0, $revenueByService[0]['revenue']);
    }

    public function test_report_shows_cancelled_and_no_show_stats(): void
    {
        $this->actingAsOwner();

        $client = Client::factory()->forTenant($this->tenant)->create();
        $service = Service::factory()->forTenant($this->tenant)->create();
        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();

        $today = Carbon::today();

        // 5 completed
        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->completed()
            ->count(5)
            ->create();

        // 3 cancelled
        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(12))
            ->cancelled()
            ->count(3)
            ->create();

        // 2 no_show
        Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(14))
            ->state(['status' => 'no_show'])
            ->count(2)
            ->create();

        $response = $this->getJson(route('dashboard.report', [
            'period' => 'today',
        ]));

        $response->assertOk();

        $metrics = $response->json('data.metrics');

        $this->assertEquals(10, $metrics['total']);
        $this->assertEquals(5, $metrics['completed']);
        $this->assertEquals(3, $metrics['cancelled']);
        $this->assertEquals(2, $metrics['no_show']);
        $this->assertEquals(30.0, $metrics['cancellation_rate']); // 3/10
        $this->assertEquals(20.0, $metrics['no_show_rate']); // 2/10
    }
}
