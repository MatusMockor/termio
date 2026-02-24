<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Contracts\Services\BookingAvailability;
use App\Contracts\Services\WorkingHoursBusiness;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class BookingAvailabilityService implements BookingAvailability
{
    public function __construct(
        private readonly AvailabilitySlotService $availabilitySlotService,
        private readonly WorkingHoursBusiness $workingHoursBusiness,
    ) {}

    /**
     * @return array<int, array{time: string, available: bool}|array{time: string, available: bool, staff_id: int}>
     */
    public function getAvailability(Tenant $tenant, int $serviceId, string $date, ?int $staffId = null): array
    {
        $parsedDate = Carbon::parse($date);

        if (! $this->isDateWithinReservationWindow($tenant, $parsedDate)) {
            return [];
        }

        $dayOfWeek = $parsedDate->dayOfWeek;
        $hasConfiguredBusinessHours = $this->workingHoursBusiness->hasConfiguredBusinessHours($tenant->id);
        $slotIntervalMinutes = $tenant->getReservationSlotIntervalMinutes();
        $minimumAllowedStartAt = $this->getMinimumAllowedStartAt($tenant, $parsedDate);

        $activeBusinessHours = $hasConfiguredBusinessHours
            ? $this->workingHoursBusiness->getActiveBusinessHours($tenant->id)
            : new EloquentCollection;

        $businessWorkingHours = $hasConfiguredBusinessHours
            ? $this->workingHoursBusiness->getBusinessHoursForDay($activeBusinessHours, $dayOfWeek)
            : null;

        $service = Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($serviceId);

        if ($staffId !== null) {
            return $this->getAvailabilityForStaff(
                $tenant->id,
                $staffId,
                $service,
                $parsedDate,
                $dayOfWeek,
                $hasConfiguredBusinessHours,
                $businessWorkingHours,
                $slotIntervalMinutes,
                $minimumAllowedStartAt,
            );
        }

        return $this->getAvailabilityForAnyStaff(
            $tenant->id,
            $service,
            $parsedDate,
            $hasConfiguredBusinessHours,
            $businessWorkingHours,
            $slotIntervalMinutes,
            $minimumAllowedStartAt,
        );
    }

    /**
     * @return array<int, array{time: string, available: bool}>
     */
    private function getAvailabilityForStaff(
        int $tenantId,
        int $staffId,
        Service $service,
        Carbon $date,
        int $dayOfWeek,
        bool $hasConfiguredBusinessHours,
        ?WorkingHours $businessWorkingHours,
        int $slotIntervalMinutes,
        Carbon $minimumAllowedStartAt,
    ): array {
        if ($this->hasAllDayTimeOff($tenantId, $date, $staffId)) {
            return [];
        }

        $workingHours = $this->getWorkingHoursForStaff(
            $tenantId,
            $staffId,
            $dayOfWeek,
            $hasConfiguredBusinessHours,
            $businessWorkingHours,
        );

        if (! $workingHours) {
            return [];
        }

        $existingAppointments = Appointment::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->forDate($date)
            ->where('staff_id', $staffId)
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->get();

        $timeOffPeriods = $this->getPartialTimeOffPeriods($tenantId, $date, $staffId);

        return $this->availabilitySlotService->generateAvailableSlots(
            $workingHours,
            $existingAppointments,
            $timeOffPeriods,
            $service->duration_minutes,
            $date,
            $slotIntervalMinutes,
            $minimumAllowedStartAt,
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @return array<int, array{time: string, available: bool, staff_id: int}>
     */
    private function getAvailabilityForAnyStaff(
        int $tenantId,
        Service $service,
        Carbon $date,
        bool $hasConfiguredBusinessHours,
        ?WorkingHours $businessWorkingHours,
        int $slotIntervalMinutes,
        Carbon $minimumAllowedStartAt,
    ): array {
        $staffIds = $this->getBookableStaffIds($tenantId, $service->id);

        if (empty($staffIds)) {
            return [];
        }

        /** @var array<string, array{time: string, available: bool, staff_id: int}> $allSlots */
        $allSlots = [];

        foreach ($staffIds as $staffId) {
            $this->appendAvailableStaffSlots(
                $allSlots,
                $tenantId,
                $staffId,
                $service,
                $date,
                $hasConfiguredBusinessHours,
                $businessWorkingHours,
                $slotIntervalMinutes,
                $minimumAllowedStartAt,
            );
        }

        ksort($allSlots);

        return array_values($allSlots);
    }

    /**
     * @return array<int, int>
     */
    private function getBookableStaffIds(int $tenantId, int $serviceId): array
    {
        return StaffProfile::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->bookable()
            ->forService($serviceId)
            ->pluck('id')
            ->toArray();
    }

    /**
     * @param  array<string, array{time: string, available: bool, staff_id: int}>  $allSlots
     */
    private function appendAvailableStaffSlots(
        array &$allSlots,
        int $tenantId,
        int $staffId,
        Service $service,
        Carbon $date,
        bool $hasConfiguredBusinessHours,
        ?WorkingHours $businessWorkingHours,
        int $slotIntervalMinutes,
        Carbon $minimumAllowedStartAt,
    ): void {
        if ($this->hasAllDayTimeOff($tenantId, $date, $staffId)) {
            return;
        }

        $workingHours = $this->getWorkingHoursForStaff(
            $tenantId,
            $staffId,
            $date->dayOfWeek,
            $hasConfiguredBusinessHours,
            $businessWorkingHours,
        );

        if ($workingHours === null) {
            return;
        }

        $existingAppointments = $this->getExistingAppointmentsForStaff($tenantId, $date, $staffId);
        $timeOffPeriods = $this->getPartialTimeOffPeriods($tenantId, $date, $staffId);

        $staffSlots = $this->availabilitySlotService->generateAvailableSlots(
            $workingHours,
            $existingAppointments,
            $timeOffPeriods,
            $service->duration_minutes,
            $date,
            $slotIntervalMinutes,
            $minimumAllowedStartAt,
        );

        foreach ($staffSlots as $slot) {
            if (! $slot['available']) {
                continue;
            }

            $slotTime = $slot['time'];
            if (isset($allSlots[$slotTime])) {
                continue;
            }

            $allSlots[$slotTime] = [
                'time' => $slotTime,
                'available' => true,
                'staff_id' => $staffId,
            ];
        }
    }

    private function getWorkingHoursForStaff(
        int $tenantId,
        int $staffId,
        int $dayOfWeek,
        bool $hasConfiguredBusinessHours,
        ?WorkingHours $businessWorkingHours
    ): ?WorkingHours {
        $staffWorkingHours = WorkingHours::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('staff_id', $staffId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        return $this->workingHoursBusiness->constrainStaffHoursByBusinessHours(
            $staffWorkingHours,
            $businessWorkingHours,
            $hasConfiguredBusinessHours,
        );
    }

    /**
     * @return EloquentCollection<int, Appointment>
     */
    private function getExistingAppointmentsForStaff(int $tenantId, Carbon $date, int $staffId): EloquentCollection
    {
        return Appointment::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->forDate($date)
            ->where('staff_id', $staffId)
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->get();
    }

    private function hasAllDayTimeOff(int $tenantId, Carbon $date, ?int $staffId): bool
    {
        return TimeOff::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->forDate($date)
            ->where(static function (Builder $query) use ($staffId): void {
                $query->whereNull('staff_id')
                    ->orWhere('staff_id', $staffId);
            })
            ->whereNull('start_time')
            ->whereNull('end_time')
            ->exists();
    }

    /**
     * @return EloquentCollection<int, TimeOff>
     */
    private function getPartialTimeOffPeriods(int $tenantId, Carbon $date, ?int $staffId): EloquentCollection
    {
        return TimeOff::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->forDate($date)
            ->where(static function (Builder $query) use ($staffId): void {
                $query->whereNull('staff_id')
                    ->orWhere('staff_id', $staffId);
            })
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get();
    }

    private function isDateWithinReservationWindow(Tenant $tenant, Carbon $date): bool
    {
        $today = now()->startOfDay();
        $latestBookableDate = $today->copy()->addDays($tenant->getReservationMaxDaysInAdvance())->endOfDay();
        $selectedDate = $date->copy()->endOfDay();

        if ($selectedDate->lt($today)) {
            return false;
        }

        return $selectedDate->lte($latestBookableDate);
    }

    private function getMinimumAllowedStartAt(Tenant $tenant, Carbon $selectedDate): Carbon
    {
        $leadTimeHours = $tenant->getReservationLeadTimeHours();
        $minimumAllowedStartAt = now()->addHours($leadTimeHours);
        $selectedDateStart = $selectedDate->copy()->startOfDay();

        if ($minimumAllowedStartAt->gt($selectedDateStart)) {
            return $minimumAllowedStartAt;
        }

        return $selectedDateStart;
    }
}
