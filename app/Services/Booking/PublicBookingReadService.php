<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Contracts\Services\PublicBookingRead;
use App\Contracts\Services\WorkingHoursBusiness;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final class PublicBookingReadService implements PublicBookingRead
{
    public function __construct(
        private readonly WorkingHoursBusiness $workingHoursBusiness,
    ) {}

    public function getTenantBySlug(string $tenantSlug): Tenant
    {
        return Tenant::where('slug', $tenantSlug)->firstOrFail();
    }

    /**
     * @return array{
     *     name: string,
     *     business_type: \App\Enums\BusinessType|null,
     *     address: string|null,
     *     phone: string|null,
     *     logo_url: string|null
     * }
     */
    public function getTenantInfo(string $tenantSlug): array
    {
        $tenant = $this->getTenantBySlug($tenantSlug);

        return [
            'name' => $tenant->name,
            'business_type' => $tenant->business_type,
            'address' => $tenant->address,
            'phone' => $tenant->phone,
            'logo_url' => $tenant->getLogoUrl(),
        ];
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(string $tenantSlug): Collection
    {
        $tenant = $this->getTenantBySlug($tenantSlug);

        return Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->bookableOnline()
            ->ordered()
            ->get(['id', 'name', 'description', 'duration_minutes', 'price', 'category']);
    }

    /**
     * @return Collection<int, StaffProfile>
     */
    public function getStaff(string $tenantSlug, ?int $serviceId = null): Collection
    {
        $tenant = $this->getTenantBySlug($tenantSlug);

        $query = StaffProfile::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->bookable()
            ->ordered();

        if ($serviceId !== null) {
            $query->forService($serviceId);
        }

        return $query->get(['id', 'display_name', 'bio', 'photo_url', 'specializations']);
    }

    /**
     * @return Collection<int, Service>
     */
    public function getStaffServices(string $tenantSlug, int $staffId): Collection
    {
        $tenant = $this->getTenantBySlug($tenantSlug);

        $staffProfile = StaffProfile::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($staffId);

        return $staffProfile->services()
            ->active()
            ->bookableOnline()
            ->ordered()
            ->get(['services.id', 'name', 'description', 'duration_minutes', 'price', 'category']);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @return array<int, string>
     */
    public function getAvailableDates(
        string $tenantSlug,
        int $serviceId,
        int $month,
        int $year,
        ?int $staffId = null
    ): array {
        $tenant = $this->getTenantBySlug($tenantSlug);

        $service = Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($serviceId);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $staffIds = $this->resolveStaffIds($tenant->id, $service->id, $staffId);
        $hasConfiguredBusinessHours = $this->workingHoursBusiness->hasConfiguredBusinessHours($tenant->id);

        $activeBusinessHours = $hasConfiguredBusinessHours
            ? $this->workingHoursBusiness->getActiveBusinessHours($tenant->id)->keyBy('day_of_week')
            : collect();

        if (empty($staffIds)) {
            return [];
        }

        $workingHoursMap = $this->getWorkingHoursMap($tenant->id, $staffIds);
        $timeOffsByDate = $this->getAllDayTimeOffsByDate($tenant->id, $staffIds, $startDate, $endDate);

        return $this->collectAvailableDates(
            $tenant,
            $staffIds,
            $workingHoursMap,
            $timeOffsByDate,
            $service->duration_minutes,
            $startDate,
            $endDate,
            $hasConfiguredBusinessHours,
            $activeBusinessHours,
        );
    }

    /**
     * @return array{id: int, display_name: string}|null
     */
    public function getStaffSummary(?int $staffId): ?array
    {
        if ($staffId === null) {
            return null;
        }

        $staff = StaffProfile::withoutTenantScope()->find($staffId);

        if ($staff === null) {
            return null;
        }

        return [
            'id' => $staff->id,
            'display_name' => $staff->display_name,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function resolveStaffIds(int $tenantId, int $serviceId, ?int $staffId): array
    {
        if ($staffId !== null) {
            return [$staffId];
        }

        return StaffProfile::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->bookable()
            ->forService($serviceId)
            ->pluck('id')
            ->toArray();
    }

    /**
     * @param  array<int, int>  $staffIds
     */
    private function getWorkingHoursMap(int $tenantId, array $staffIds): SupportCollection
    {
        return WorkingHours::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereIn('staff_id', $staffIds)
            ->where('is_active', true)
            ->get()
            ->groupBy(static fn (WorkingHours $wh): string => $wh->staff_id.'_'.$wh->day_of_week);
    }

    /**
     * @param  array<int, int>  $staffIds
     */
    private function getAllDayTimeOffsByDate(
        int $tenantId,
        array $staffIds,
        Carbon $startDate,
        Carbon $endDate
    ): SupportCollection {
        return TimeOff::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where(static function (Builder $query) use ($staffIds): void {
                $query->whereNull('staff_id')
                    ->orWhereIn('staff_id', $staffIds);
            })
            ->where('date', '>=', $startDate->toDateString())
            ->where('date', '<=', $endDate->toDateString())
            ->whereNull('start_time')
            ->whereNull('end_time')
            ->get()
            ->groupBy('date');
    }

    /**
     * @param  array<int, int>  $staffIds
     * @return array<int, string>
     */
    private function collectAvailableDates(
        Tenant $tenant,
        array $staffIds,
        SupportCollection $workingHoursMap,
        SupportCollection $timeOffsByDate,
        int $serviceDurationMinutes,
        Carbon $startDate,
        Carbon $endDate,
        bool $hasConfiguredBusinessHours,
        SupportCollection $activeBusinessHours,
    ): array {
        $availableDates = [];
        $currentDate = $startDate->copy();
        $today = Carbon::today();
        $latestBookableDate = $today->copy()->addDays($tenant->getReservationMaxDaysInAdvance())->endOfDay();

        while ($currentDate <= $endDate) {
            if ($currentDate < $today) {
                $currentDate->addDay();

                continue;
            }

            if ($currentDate->gt($latestBookableDate)) {
                $currentDate->addDay();

                continue;
            }

            $dateStr = $currentDate->toDateString();
            if ($this->hasAvailabilityForDate(
                $tenant,
                $dateStr,
                $currentDate->dayOfWeek,
                $staffIds,
                $workingHoursMap,
                $timeOffsByDate,
                $serviceDurationMinutes,
                $hasConfiguredBusinessHours,
                $activeBusinessHours
            )) {
                $availableDates[] = $dateStr;
            }

            $currentDate->addDay();
        }

        return $availableDates;
    }

    /**
     * @param  array<int, int>  $staffIds
     */
    private function hasAvailabilityForDate(
        Tenant $tenant,
        string $dateStr,
        int $dayOfWeek,
        array $staffIds,
        SupportCollection $workingHoursMap,
        SupportCollection $timeOffsByDate,
        int $serviceDurationMinutes,
        bool $hasConfiguredBusinessHours,
        SupportCollection $activeBusinessHours
    ): bool {
        if ($hasConfiguredBusinessHours && ! $activeBusinessHours->has($dayOfWeek)) {
            return false;
        }

        $dateTimeOffs = $timeOffsByDate->get($dateStr, collect());
        $businessWorkingHours = $activeBusinessHours->get($dayOfWeek);
        $date = Carbon::parse($dateStr);
        $minimumAllowedStartAt = now()->addHours($tenant->getReservationLeadTimeHours());
        $slotIntervalMinutes = $tenant->getReservationSlotIntervalMinutes();

        foreach ($staffIds as $checkStaffId) {
            if ($this->isAllDayOffForStaff($dateTimeOffs, $checkStaffId)) {
                continue;
            }

            $staffWorkingHoursForDay = $workingHoursMap->get($checkStaffId.'_'.$dayOfWeek);
            $staffWorkingHours = $staffWorkingHoursForDay?->first();

            if (! $staffWorkingHours instanceof WorkingHours) {
                continue;
            }

            $constrainedWorkingHours = $this->workingHoursBusiness->constrainStaffHoursByBusinessHours(
                $staffWorkingHours,
                $businessWorkingHours,
                $hasConfiguredBusinessHours,
            );

            if (! $constrainedWorkingHours) {
                continue;
            }

            if ($this->hasBookableSlotAfterLeadTime(
                $constrainedWorkingHours,
                $date,
                $serviceDurationMinutes,
                $slotIntervalMinutes,
                $minimumAllowedStartAt,
            )) {
                return true;
            }
        }

        return false;
    }

    private function isAllDayOffForStaff(SupportCollection $dateTimeOffs, int $staffId): bool
    {
        return $dateTimeOffs->contains(
            static fn (TimeOff $timeOff): bool => $timeOff->staff_id === null || $timeOff->staff_id === $staffId
        );
    }

    private function hasBookableSlotAfterLeadTime(
        WorkingHours $workingHours,
        Carbon $date,
        int $serviceDurationMinutes,
        int $slotIntervalMinutes,
        Carbon $minimumAllowedStartAt,
    ): bool {
        $startTime = Carbon::parse($date->format('Y-m-d').' '.$workingHours->start_time);
        $endTime = Carbon::parse($date->format('Y-m-d').' '.$workingHours->end_time);
        $current = $startTime->copy();

        while ($current->copy()->addMinutes($serviceDurationMinutes)->lte($endTime)) {
            if ($current->gte($minimumAllowedStartAt)) {
                return true;
            }

            $current->addMinutes($slotIntervalMinutes);
        }

        return false;
    }
}
