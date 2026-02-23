<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface WorkingHoursBusiness
{
    public function hasConfiguredBusinessHours(int $tenantId): bool;

    /**
     * @return Collection<int, WorkingHours>
     */
    public function getActiveBusinessHours(int $tenantId): Collection;

    public function getBusinessHoursForDay(Collection $activeBusinessHours, int $dayOfWeek): ?WorkingHours;

    public function constrainStaffHoursByBusinessHours(
        ?WorkingHours $staffWorkingHours,
        ?WorkingHours $businessWorkingHours,
        bool $hasConfiguredBusinessHours
    ): ?WorkingHours;

    public function isIntervalWithinBusinessHours(
        Carbon $startsAt,
        Carbon $endsAt,
        ?WorkingHours $businessWorkingHours,
        bool $hasConfiguredBusinessHours
    ): bool;
}
