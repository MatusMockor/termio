<?php

declare(strict_types=1);

namespace App\Services\WorkingHours;

use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class BusinessWorkingHoursService
{
    public function hasConfiguredBusinessHours(int $tenantId): bool
    {
        return WorkingHours::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereNull('staff_id')
            ->exists();
    }

    /**
     * @return Collection<int, WorkingHours>
     */
    public function getActiveBusinessHours(int $tenantId): Collection
    {
        return WorkingHours::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereNull('staff_id')
            ->where('is_active', true)
            ->get();
    }

    public function getBusinessHoursForDay(Collection $activeBusinessHours, int $dayOfWeek): ?WorkingHours
    {
        $businessHours = $activeBusinessHours->firstWhere('day_of_week', $dayOfWeek);

        if (! $businessHours instanceof WorkingHours) {
            return null;
        }

        return $businessHours;
    }

    public function constrainStaffHoursByBusinessHours(
        ?WorkingHours $staffWorkingHours,
        ?WorkingHours $businessWorkingHours,
        bool $hasConfiguredBusinessHours
    ): ?WorkingHours {
        if (! $staffWorkingHours) {
            return null;
        }

        if (! $hasConfiguredBusinessHours) {
            return $staffWorkingHours;
        }

        if (! $businessWorkingHours) {
            return null;
        }

        $startTime = max($staffWorkingHours->start_time, $businessWorkingHours->start_time);
        $endTime = min($staffWorkingHours->end_time, $businessWorkingHours->end_time);

        if ($startTime >= $endTime) {
            return null;
        }

        $constrainedWorkingHours = $staffWorkingHours->replicate();
        $constrainedWorkingHours->start_time = $startTime;
        $constrainedWorkingHours->end_time = $endTime;

        return $constrainedWorkingHours;
    }

    public function isIntervalWithinBusinessHours(
        Carbon $startsAt,
        Carbon $endsAt,
        ?WorkingHours $businessWorkingHours,
        bool $hasConfiguredBusinessHours
    ): bool {
        if (! $hasConfiguredBusinessHours) {
            return true;
        }

        if (! $businessWorkingHours) {
            return false;
        }

        $startTime = $startsAt->format('H:i');
        $endTime = $endsAt->format('H:i');

        return $startTime >= $businessWorkingHours->start_time
            && $endTime <= $businessWorkingHours->end_time;
    }
}
