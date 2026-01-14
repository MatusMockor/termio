<?php

declare(strict_types=1);

namespace App\DTOs\Reporting;

final readonly class FullReportDTO
{
    /**
     * @param  array<StaffPerformanceDTO>  $topStaffByRevenue
     * @param  array<StaffPerformanceDTO>  $topStaffByAppointments
     * @param  array<ServiceRevenueDTO>  $revenueByService
     */
    public function __construct(
        public DateRangeDTO $period,
        public ReportMetricsDTO $metrics,
        public array $topStaffByRevenue,
        public array $topStaffByAppointments,
        public array $revenueByService,
        public ?PeriodComparisonDTO $comparison,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => [
                'start' => $this->period->startDate->toDateString(),
                'end' => $this->period->endDate->toDateString(),
            ],
            'metrics' => $this->metrics->toArray(),
            'top_staff_by_revenue' => array_map(
                static fn (StaffPerformanceDTO $staff): array => $staff->toArray(),
                $this->topStaffByRevenue
            ),
            'top_staff_by_appointments' => array_map(
                static fn (StaffPerformanceDTO $staff): array => $staff->toArray(),
                $this->topStaffByAppointments
            ),
            'revenue_by_service' => array_map(
                static fn (ServiceRevenueDTO $service): array => $service->toArray(),
                $this->revenueByService
            ),
            'comparison' => $this->comparison?->toArray(),
        ];
    }
}
