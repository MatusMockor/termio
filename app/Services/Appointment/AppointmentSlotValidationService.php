<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Contracts\Services\BookingAvailability;
use App\Contracts\Services\WorkingHoursBusiness;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class AppointmentSlotValidationService
{
    public function __construct(
        private readonly BookingAvailability $bookingAvailability,
        private readonly WorkingHoursBusiness $workingHoursBusiness,
    ) {}

    public function ensureBookable(
        Tenant $tenant,
        Service $service,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $staffId,
    ): void {
        $this->ensureWithinReservationWindow($tenant, $startsAt);
        $this->ensureWithinBusinessWorkingHours($tenant, $startsAt, $endsAt);
        $this->ensureSlotAvailable($tenant, $service, $startsAt, $staffId);
    }

    private function ensureSlotAvailable(
        Tenant $tenant,
        Service $service,
        Carbon $startsAt,
        ?int $staffId,
    ): void {
        $slots = $this->bookingAvailability->getAvailability(
            $tenant,
            $service->id,
            $startsAt->toDateString(),
            $staffId,
        );

        $selectedTime = $startsAt->format('H:i');

        foreach ($slots as $slot) {
            if ($slot['time'] === $selectedTime && $slot['available'] === true) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'starts_at' => ['Selected time slot is not available.'],
        ]);
    }

    private function ensureWithinBusinessWorkingHours(Tenant $tenant, Carbon $startsAt, Carbon $endsAt): void
    {
        $hasConfiguredBusinessHours = $this->workingHoursBusiness->hasConfiguredBusinessHours($tenant->id);

        $businessWorkingHours = null;

        if ($hasConfiguredBusinessHours) {
            $activeBusinessHours = $this->workingHoursBusiness->getActiveBusinessHours($tenant->id);
            $businessWorkingHours = $this->workingHoursBusiness
                ->getBusinessHoursForDay($activeBusinessHours, $startsAt->dayOfWeek);
        }

        if ($this->workingHoursBusiness->isIntervalWithinBusinessHours(
            $startsAt,
            $endsAt,
            $businessWorkingHours,
            $hasConfiguredBusinessHours,
        )) {
            return;
        }

        throw ValidationException::withMessages([
            'starts_at' => ['Selected time is outside business opening hours.'],
        ]);
    }

    private function ensureWithinReservationWindow(Tenant $tenant, Carbon $startsAt): void
    {
        $now = now();
        $minimumAllowedStartAt = $now->copy()->addHours($tenant->getReservationLeadTimeHours());

        if ($startsAt->lt($minimumAllowedStartAt)) {
            throw ValidationException::withMessages([
                'starts_at' => ['Selected time is too soon. Please choose a later time.'],
            ]);
        }

        $latestAllowedStartAt = $now->copy()->startOfDay()
            ->addDays($tenant->getReservationMaxDaysInAdvance())
            ->endOfDay();

        if ($startsAt->lte($latestAllowedStartAt)) {
            return;
        }

        throw ValidationException::withMessages([
            'starts_at' => ['Selected time is too far in the future.'],
        ]);
    }
}
