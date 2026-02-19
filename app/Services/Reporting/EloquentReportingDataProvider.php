<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Contracts\Services\ReportingDataProvider;
use App\DTOs\Reporting\DateRangeDTO;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\StaffProfile;
use App\Models\WorkingHours;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class EloquentReportingDataProvider implements ReportingDataProvider
{
    /**
     * @return Collection<int, Appointment>
     */
    public function getAppointments(DateRangeDTO $range): Collection
    {
        return Appointment::with(['service', 'staff', 'client'])
            ->forDateRange($range->startDate, $range->endDate)
            ->get();
    }

    public function getNewClientCount(DateRangeDTO $range): int
    {
        return Client::whereBetween('created_at', [
            $range->startDate,
            $range->endDate,
        ])->count();
    }

    public function getReturningClientCount(DateRangeDTO $range): int
    {
        $appointmentsInRange = Appointment::forDateRange($range->startDate, $range->endDate)
            ->whereIn('status', ['completed', 'confirmed', 'pending'])
            ->pluck('client_id')
            ->unique();

        return Client::whereIn('id', $appointmentsInRange)
            ->where('created_at', '<', $range->startDate)
            ->count();
    }

    /**
     * @return Collection<int, EloquentCollection<int, WorkingHours>>
     */
    public function getWorkingHoursByDay(?int $staffId = null): Collection
    {
        $query = WorkingHours::active();

        if ($staffId !== null) {
            $query->where('staff_id', $staffId);
        }

        return collect($query->get()->groupBy('day_of_week'));
    }

    /**
     * @param  array<int, int|string>  $staffIds
     * @return Collection<int, StaffProfile>
     */
    public function getStaffProfilesByIds(array $staffIds): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        return StaffProfile::whereIn('id', $staffIds)->get()->keyBy('id');
    }
}
