<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Reporting\DateRangeDTO;
use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Models\WorkingHours;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

interface ReportingDataProvider
{
    /**
     * @return Collection<int, Appointment>
     */
    public function getAppointments(DateRangeDTO $range): Collection;

    public function getNewClientCount(DateRangeDTO $range): int;

    public function getReturningClientCount(DateRangeDTO $range): int;

    /**
     * @return Collection<int, EloquentCollection<int, WorkingHours>>
     */
    public function getWorkingHoursByDay(?int $staffId = null): Collection;

    /**
     * @param  array<int, int|string>  $staffIds
     * @return Collection<int, StaffProfile>
     */
    public function getStaffProfilesByIds(array $staffIds): Collection;
}
