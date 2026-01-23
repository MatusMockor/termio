<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Reporting\DateRangeDTO;
use App\DTOs\Reporting\FullReportDTO;
use App\DTOs\Reporting\PeriodComparisonDTO;
use App\DTOs\Reporting\ReportMetricsDTO;
use App\DTOs\Reporting\ServiceRevenueDTO;
use App\DTOs\Reporting\StaffPerformanceDTO;

interface ReportingMetricsService
{
    public function generateFullReport(DateRangeDTO $range): FullReportDTO;

    public function generateFullReportWithoutComparison(DateRangeDTO $range): FullReportDTO;

    public function calculateMetrics(DateRangeDTO $range): ReportMetricsDTO;

    /**
     * @return array<StaffPerformanceDTO>
     */
    public function getTopStaffByRevenue(DateRangeDTO $range, int $limit = 5): array;

    /**
     * @return array<StaffPerformanceDTO>
     */
    public function getTopStaffByAppointments(DateRangeDTO $range, int $limit = 5): array;

    /**
     * @return array<ServiceRevenueDTO>
     */
    public function getRevenueByService(DateRangeDTO $range): array;

    public function comparePeriods(DateRangeDTO $current): PeriodComparisonDTO;

    public function calculateOccupancyRate(DateRangeDTO $range): float;
}
