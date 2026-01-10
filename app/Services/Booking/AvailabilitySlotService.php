<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\TimeOff;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class AvailabilitySlotService
{
    private const int SLOT_INTERVAL_MINUTES = 30;

    /**
     * Generate available time slots for a given date based on working hours and existing appointments.
     *
     * @param  Collection<int, Appointment>  $existingAppointments
     * @param  Collection<int, TimeOff>  $timeOffPeriods
     * @return array<array{time: string, available: bool}>
     */
    public function generateAvailableSlots(
        WorkingHours $workingHours,
        Collection $existingAppointments,
        Collection $timeOffPeriods,
        int $serviceDurationMinutes,
        Carbon $date,
    ): array {
        $slots = [];

        $startTime = Carbon::parse($date->format('Y-m-d').' '.$workingHours->start_time);
        $endTime = Carbon::parse($date->format('Y-m-d').' '.$workingHours->end_time);

        $current = $startTime->copy();

        while ($current->copy()->addMinutes($serviceDurationMinutes)->lte($endTime)) {
            $slotEnd = $current->copy()->addMinutes($serviceDurationMinutes);

            $isAvailable = $this->isSlotAvailable(
                $current,
                $slotEnd,
                $date,
                $existingAppointments,
                $timeOffPeriods,
            );

            $slots[] = [
                'time' => $current->format('H:i'),
                'available' => $isAvailable,
            ];

            $current->addMinutes(self::SLOT_INTERVAL_MINUTES);
        }

        return $slots;
    }

    /**
     * Check if a specific time slot is available.
     *
     * @param  Collection<int, Appointment>  $appointments
     * @param  Collection<int, TimeOff>  $timeOffPeriods
     */
    public function isSlotAvailable(
        Carbon $slotStart,
        Carbon $slotEnd,
        Carbon $date,
        Collection $appointments,
        Collection $timeOffPeriods,
    ): bool {
        if ($slotStart->lt(now())) {
            return false;
        }

        if ($this->hasConflictingAppointment($slotStart, $slotEnd, $appointments)) {
            return false;
        }

        if ($this->hasTimeOffConflict($slotStart, $slotEnd, $date, $timeOffPeriods)) {
            return false;
        }

        return true;
    }

    /**
     * Check if slot conflicts with any existing appointment.
     *
     * @param  Collection<int, Appointment>  $appointments
     */
    private function hasConflictingAppointment(
        Carbon $slotStart,
        Carbon $slotEnd,
        Collection $appointments,
    ): bool {
        foreach ($appointments as $appointment) {
            if ($slotStart->lt($appointment->ends_at) && $slotEnd->gt($appointment->starts_at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if slot conflicts with any time off period.
     *
     * @param  Collection<int, TimeOff>  $timeOffPeriods
     */
    private function hasTimeOffConflict(
        Carbon $slotStart,
        Carbon $slotEnd,
        Carbon $date,
        Collection $timeOffPeriods,
    ): bool {
        foreach ($timeOffPeriods as $timeOff) {
            $timeOffStart = Carbon::parse($date->format('Y-m-d').' '.$timeOff->start_time);
            $timeOffEnd = Carbon::parse($date->format('Y-m-d').' '.$timeOff->end_time);

            if ($slotStart->lt($timeOffEnd) && $slotEnd->gt($timeOffStart)) {
                return true;
            }
        }

        return false;
    }
}
