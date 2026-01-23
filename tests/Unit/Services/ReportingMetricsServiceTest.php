<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Services\ReportingMetricsService;
use App\DTOs\Reporting\DateRangeDTO;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportingMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportingMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportingMetricsService::class);
    }

    public function test_calculate_metrics_returns_correct_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->forTenant($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create(['price' => 50.00]);
        $staff = StaffProfile::factory()->forTenant($tenant)->create();

        $today = Carbon::today();

        // 3 completed, 1 cancelled, 1 no_show
        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->completed()
            ->create();

        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(10))
            ->completed()
            ->create();

        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(11))
            ->completed()
            ->create();

        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(12))
            ->cancelled()
            ->create();

        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(13))
            ->state(['status' => 'no_show'])
            ->create();

        $range = new DateRangeDTO(
            startDate: $today->copy()->startOfDay(),
            endDate: $today->copy()->endOfDay(),
        );

        $metrics = $this->service->calculateMetrics($range);

        $this->assertEquals(5, $metrics->appointments->total);
        $this->assertEquals(3, $metrics->appointments->completed);
        $this->assertEquals(1, $metrics->appointments->cancelled);
        $this->assertEquals(1, $metrics->appointments->noShow);
        $this->assertEquals(150.00, $metrics->totalRevenue);
    }

    public function test_calculate_metrics_returns_correct_rates(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->forTenant($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create();
        $staff = StaffProfile::factory()->forTenant($tenant)->create();

        $today = Carbon::today();

        // 6 completed, 2 cancelled, 2 no_show = 10 total
        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->completed()
            ->count(6)
            ->create();

        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(10))
            ->cancelled()
            ->count(2)
            ->create();

        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(11))
            ->state(['status' => 'no_show'])
            ->count(2)
            ->create();

        $range = new DateRangeDTO(
            startDate: $today->copy()->startOfDay(),
            endDate: $today->copy()->endOfDay(),
        );

        $metrics = $this->service->calculateMetrics($range);

        $this->assertEquals(20.0, $metrics->appointments->noShowRate); // 2/10 = 20%
        $this->assertEquals(20.0, $metrics->appointments->cancellationRate); // 2/10 = 20%
    }

    public function test_calculate_metrics_handles_empty_period(): void
    {
        $range = new DateRangeDTO(
            startDate: Carbon::yesterday(),
            endDate: Carbon::yesterday()->endOfDay(),
        );

        $metrics = $this->service->calculateMetrics($range);

        $this->assertEquals(0, $metrics->appointments->total);
        $this->assertEquals(0.0, $metrics->totalRevenue);
        $this->assertEquals(0.0, $metrics->appointments->noShowRate);
        $this->assertEquals(0.0, $metrics->appointments->cancellationRate);
    }

    public function test_calculate_occupancy_rate_considers_working_hours(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = StaffProfile::factory()->forTenant($tenant)->create();
        $client = Client::factory()->forTenant($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create(['duration_minutes' => 60]);

        $today = Carbon::today();
        $dayOfWeek = $today->dayOfWeek;

        // Working hours: 9-12 (180 minutes)
        WorkingHours::factory()
            ->forTenant($tenant)
            ->forDay($dayOfWeek)
            ->hours('09:00', '12:00')
            ->create();

        // 1 hour appointment = 60 minutes booked out of 180 = 33.33%
        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->confirmed()
            ->create();

        $range = new DateRangeDTO(
            startDate: $today->copy()->startOfDay(),
            endDate: $today->copy()->endOfDay(),
        );

        $occupancy = $this->service->calculateOccupancyRate($range);

        $this->assertEqualsWithDelta(33.33, $occupancy, 0.1);
    }

    public function test_get_client_metrics_distinguishes_new_and_returning(): void
    {
        $tenant = Tenant::factory()->create();
        $service = Service::factory()->forTenant($tenant)->create();
        $staff = StaffProfile::factory()->forTenant($tenant)->create();

        $today = Carbon::today();

        // New client (created today)
        $newClient = Client::factory()->forTenant($tenant)->create([
            'created_at' => $today->copy()->setHour(8),
        ]);

        // Returning client (created last week)
        $returningClient = Client::factory()->forTenant($tenant)->create([
            'created_at' => $today->copy()->subWeek(),
        ]);

        // Appointments for both clients
        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($newClient)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->completed()
            ->create();

        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($returningClient)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(10))
            ->completed()
            ->create();

        $range = new DateRangeDTO(
            startDate: $today->copy()->startOfDay(),
            endDate: $today->copy()->endOfDay(),
        );

        $metrics = $this->service->calculateMetrics($range);

        $this->assertEquals(1, $metrics->clients->newClients);
        $this->assertEquals(1, $metrics->clients->returningClients);
        $this->assertEquals(50.0, $metrics->clients->newClientRatio);
    }

    public function test_compare_periods_calculates_changes(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->forTenant($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create(['price' => 100.00]);
        $staff = StaffProfile::factory()->forTenant($tenant)->create();

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Yesterday: 1 completed appointment (100)
        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($yesterday->copy()->setHour(9))
            ->completed()
            ->create();

        // Today: 2 completed appointments (200)
        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->completed()
            ->count(2)
            ->create();

        $range = new DateRangeDTO(
            startDate: $today->copy()->startOfDay(),
            endDate: $today->copy()->endOfDay(),
        );

        $comparison = $this->service->comparePeriods($range);

        // Revenue: 200 vs 100 = +100%
        $this->assertEquals(100.0, $comparison->revenueChange);

        // Appointments: 2 vs 1 = +100%
        $this->assertEquals(100.0, $comparison->appointmentChange);
    }

    public function test_get_revenue_by_service_groups_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->forTenant($tenant)->create();
        $staff = StaffProfile::factory()->forTenant($tenant)->create();

        $service1 = Service::factory()->forTenant($tenant)->create([
            'name' => 'Service A',
            'price' => 30.00,
            'category' => 'Category A',
        ]);

        $service2 = Service::factory()->forTenant($tenant)->create([
            'name' => 'Service B',
            'price' => 50.00,
            'category' => 'Category B',
        ]);

        $today = Carbon::today();

        // 2 appointments for service1 = 60
        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service1)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->completed()
            ->count(2)
            ->create();

        // 3 appointments for service2 = 150
        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service2)
            ->forStaff($staff)
            ->at($today->copy()->setHour(11))
            ->completed()
            ->count(3)
            ->create();

        $range = new DateRangeDTO(
            startDate: $today->copy()->startOfDay(),
            endDate: $today->copy()->endOfDay(),
        );

        $revenueByService = $this->service->getRevenueByService($range);

        $this->assertCount(2, $revenueByService);

        // Should be sorted by revenue descending
        $this->assertEquals('Service B', $revenueByService[0]->serviceName);
        $this->assertEquals(150.0, $revenueByService[0]->revenue);
        $this->assertEquals(3, $revenueByService[0]->count);

        $this->assertEquals('Service A', $revenueByService[1]->serviceName);
        $this->assertEquals(60.0, $revenueByService[1]->revenue);
        $this->assertEquals(2, $revenueByService[1]->count);
    }

    public function test_generate_full_report_includes_all_sections(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->forTenant($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create();
        $staff = StaffProfile::factory()->forTenant($tenant)->create();

        $today = Carbon::today();

        Appointment::factory()
            ->forTenant($tenant)
            ->forClient($client)
            ->forService($service)
            ->forStaff($staff)
            ->at($today->copy()->setHour(9))
            ->completed()
            ->create();

        $range = new DateRangeDTO(
            startDate: $today->copy()->startOfDay(),
            endDate: $today->copy()->endOfDay(),
        );

        $report = $this->service->generateFullReport($range);

        $this->assertNotNull($report->period);
        $this->assertNotNull($report->metrics);
        $this->assertIsArray($report->topStaffByRevenue);
        $this->assertIsArray($report->topStaffByAppointments);
        $this->assertIsArray($report->revenueByService);
        $this->assertNotNull($report->comparison);
    }

    public function test_generate_full_report_without_comparison(): void
    {
        $range = new DateRangeDTO(
            startDate: Carbon::today()->startOfDay(),
            endDate: Carbon::today()->endOfDay(),
        );

        $report = $this->service->generateFullReportWithoutComparison($range);

        $this->assertNull($report->comparison);
    }
}
