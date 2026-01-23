<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Contracts\Services\ReportingMetricsService as ReportingMetricsServiceContract;
use App\DTOs\Reporting\AppointmentMetricsDTO;
use App\DTOs\Reporting\ClientMetricsDTO;
use App\DTOs\Reporting\DateRangeDTO;
use App\DTOs\Reporting\FullReportDTO;
use App\DTOs\Reporting\PeriodComparisonDTO;
use App\DTOs\Reporting\ReportMetricsDTO;
use App\DTOs\Reporting\ServiceRevenueDTO;
use App\DTOs\Reporting\StaffPerformanceDTO;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\StaffProfile;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ReportingMetricsService implements ReportingMetricsServiceContract
{
    public function generateFullReport(DateRangeDTO $range): FullReportDTO
    {
        return $this->buildReport($range, $this->comparePeriods($range));
    }

    public function generateFullReportWithoutComparison(DateRangeDTO $range): FullReportDTO
    {
        return $this->buildReport($range, null);
    }

    private function buildReport(DateRangeDTO $range, ?PeriodComparisonDTO $comparison): FullReportDTO
    {
        return new FullReportDTO(
            period: $range,
            metrics: $this->calculateMetrics($range),
            topStaffByRevenue: $this->getTopStaffByRevenue($range),
            topStaffByAppointments: $this->getTopStaffByAppointments($range),
            revenueByService: $this->getRevenueByService($range),
            comparison: $comparison,
        );
    }

    public function calculateMetrics(DateRangeDTO $range): ReportMetricsDTO
    {
        $appointments = $this->getAppointments($range);

        $totalAppointments = $appointments->count();
        $completedAppointments = $appointments->where('status', 'completed')->count();
        $cancelledAppointments = $appointments->where('status', 'cancelled')->count();
        $noShowAppointments = $appointments->where('status', 'no_show')->count();

        $totalRevenue = $appointments
            ->where('status', 'completed')
            ->sum(static fn (Appointment $apt): float => (float) ($apt->service->price ?? 0));

        $occupancyRate = $this->calculateOccupancyRate($range);
        $noShowRate = $totalAppointments > 0 ? ($noShowAppointments / $totalAppointments) * 100 : 0;
        $cancellationRate = $totalAppointments > 0 ? ($cancelledAppointments / $totalAppointments) * 100 : 0;

        [$newClients, $returningClients] = $this->getClientMetrics($range);
        $totalClients = $newClients + $returningClients;
        $newClientRatio = $totalClients > 0 ? ($newClients / $totalClients) * 100 : 0;

        return new ReportMetricsDTO(
            totalRevenue: $totalRevenue,
            appointments: new AppointmentMetricsDTO(
                total: $totalAppointments,
                completed: $completedAppointments,
                cancelled: $cancelledAppointments,
                noShow: $noShowAppointments,
                occupancyRate: $occupancyRate,
                noShowRate: $noShowRate,
                cancellationRate: $cancellationRate,
            ),
            clients: new ClientMetricsDTO(
                newClients: $newClients,
                returningClients: $returningClients,
                newClientRatio: $newClientRatio,
            ),
        );
    }

    /**
     * @return array<StaffPerformanceDTO>
     */
    public function getTopStaffByRevenue(DateRangeDTO $range, int $limit = 5): array
    {
        $appointments = $this->getAppointments($range)->where('status', 'completed');
        $staffMetrics = $this->aggregateStaffMetrics($appointments, $range);

        return $staffMetrics
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<StaffPerformanceDTO>
     */
    public function getTopStaffByAppointments(DateRangeDTO $range, int $limit = 5): array
    {
        $appointments = $this->getAppointments($range);
        $staffMetrics = $this->aggregateStaffMetrics($appointments, $range);

        return $staffMetrics
            ->sortByDesc('appointmentCount')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<ServiceRevenueDTO>
     */
    public function getRevenueByService(DateRangeDTO $range): array
    {
        $appointments = $this->getAppointments($range)
            ->where('status', 'completed')
            ->groupBy('service_id');

        $result = [];
        foreach ($appointments as $serviceId => $group) {
            $service = $group->first()->service;

            $result[] = new ServiceRevenueDTO(
                serviceId: $serviceId,
                serviceName: $service->name,
                category: $service->category,
                revenue: $group->sum(static fn (Appointment $apt): float => (float) $apt->service->price),
                count: $group->count(),
            );
        }

        usort($result, static fn (ServiceRevenueDTO $left, ServiceRevenueDTO $right): int => $right->revenue <=> $left->revenue);

        return $result;
    }

    public function comparePeriods(DateRangeDTO $current): PeriodComparisonDTO
    {
        $previous = $current->getPreviousPeriod();
        $currentMetrics = $this->calculateMetrics($current);
        $previousMetrics = $this->calculateMetrics($previous);

        $revenueChange = $this->calculatePercentageChange(
            $previousMetrics->totalRevenue,
            $currentMetrics->totalRevenue
        );

        $appointmentChange = $this->calculatePercentageChange(
            $previousMetrics->appointments->total,
            $currentMetrics->appointments->total
        );

        $occupancyChange = $currentMetrics->appointments->occupancyRate - $previousMetrics->appointments->occupancyRate;

        return new PeriodComparisonDTO(
            previousPeriod: $previousMetrics,
            revenueChange: $revenueChange,
            appointmentChange: $appointmentChange,
            occupancyChange: $occupancyChange,
        );
    }

    public function calculateOccupancyRate(DateRangeDTO $range): float
    {
        $availableMinutes = $this->calculateAvailableMinutes($range);

        if ($availableMinutes === 0) {
            return 0.0;
        }

        $bookedMinutes = $this->getAppointments($range)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->sum(static fn (Appointment $apt): int => $apt->duration_minutes);

        return ($bookedMinutes / $availableMinutes) * 100;
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function getAppointments(DateRangeDTO $range): Collection
    {
        return Appointment::with(['service', 'staff', 'client'])
            ->forDateRange($range->startDate, $range->endDate)
            ->get();
    }

    /**
     * @return array{int, int} [newClients, returningClients]
     */
    private function getClientMetrics(DateRangeDTO $range): array
    {
        $newClients = Client::whereBetween('created_at', [
            $range->startDate,
            $range->endDate,
        ])->count();

        $appointmentsInRange = Appointment::forDateRange($range->startDate, $range->endDate)
            ->whereIn('status', ['completed', 'confirmed', 'pending'])
            ->pluck('client_id')
            ->unique();

        $clientsCreatedBefore = Client::whereIn('id', $appointmentsInRange)
            ->where('created_at', '<', $range->startDate)
            ->count();

        return [$newClients, $clientsCreatedBefore];
    }

    private function calculateAvailableMinutes(DateRangeDTO $range): int
    {
        $totalMinutes = 0;
        $current = $range->startDate->copy();

        $workingHoursByDay = WorkingHours::active()
            ->get()
            ->groupBy('day_of_week');

        while ($current <= $range->endDate) {
            $dayOfWeek = $current->dayOfWeek;
            $dayHours = $workingHoursByDay->get($dayOfWeek, collect());

            foreach ($dayHours as $wh) {
                $start = Carbon::parse($wh->start_time);
                $end = Carbon::parse($wh->end_time);
                $totalMinutes += (int) $start->diffInMinutes($end);
            }

            $current->addDay();
        }

        return $totalMinutes;
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return Collection<int, StaffPerformanceDTO>
     */
    private function aggregateStaffMetrics(Collection $appointments, DateRangeDTO $range): Collection
    {
        $grouped = $appointments->groupBy('staff_id');
        $staffProfiles = StaffProfile::whereIn('id', $grouped->keys())->get()->keyBy('id');

        return $grouped->map(function (Collection $staffAppointments, int $staffId) use ($staffProfiles, $range): ?StaffPerformanceDTO {
            $staff = $staffProfiles->get($staffId);
            if (! $staff) {
                return null;
            }

            $completedAppointments = $staffAppointments->where('status', 'completed');
            $revenue = $completedAppointments->sum(
                static fn (Appointment $apt): float => (float) ($apt->service->price ?? 0)
            );

            $staffAvailableMinutes = $this->calculateStaffAvailableMinutes($staffId, $range);
            $bookedMinutes = $staffAppointments
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->sum(static fn (Appointment $apt): int => $apt->duration_minutes);

            $occupancyRate = $staffAvailableMinutes > 0
                ? ($bookedMinutes / $staffAvailableMinutes) * 100
                : 0;

            return new StaffPerformanceDTO(
                staffId: $staffId,
                staffName: $staff->display_name,
                revenue: $revenue,
                appointmentCount: $staffAppointments->count(),
                completedCount: $completedAppointments->count(),
                occupancyRate: $occupancyRate,
            );
        })->filter()->values();
    }

    private function calculateStaffAvailableMinutes(int $staffId, DateRangeDTO $range): int
    {
        $totalMinutes = 0;
        $current = $range->startDate->copy();

        $workingHoursByDay = WorkingHours::active()
            ->where('staff_id', $staffId)
            ->get()
            ->groupBy('day_of_week');

        while ($current <= $range->endDate) {
            $dayOfWeek = $current->dayOfWeek;
            $dayHours = $workingHoursByDay->get($dayOfWeek, collect());

            foreach ($dayHours as $wh) {
                $start = Carbon::parse($wh->start_time);
                $end = Carbon::parse($wh->end_time);
                $totalMinutes += (int) $start->diffInMinutes($end);
            }

            $current->addDay();
        }

        return $totalMinutes;
    }

    private function calculatePercentageChange(float $previous, float $current): float
    {
        if ($previous === 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
