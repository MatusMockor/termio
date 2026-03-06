<?php

declare(strict_types=1);

namespace App\Actions\Waitlist;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\WaitlistEntry;
use Illuminate\Validation\ValidationException;

final class AppointmentReplaceFromWaitlistAction
{
    public function __construct(
        private readonly WaitlistConvertToAppointmentAction $convertAction,
    ) {}

    public function handle(
        Appointment $appointment,
        WaitlistEntry $entry,
        ?string $notes = null,
    ): Appointment {
        if ($entry->tenant_id !== $appointment->tenant_id) {
            throw ValidationException::withMessages([
                'waitlist_entry_id' => ['Waitlist entry does not belong to the selected appointment tenant.'],
            ]);
        }

        if ($appointment->status !== AppointmentStatus::Cancelled->value) {
            throw ValidationException::withMessages([
                'appointment' => ['Only cancelled appointments can be replaced from waitlist.'],
            ]);
        }

        if ($entry->service_id !== $appointment->service_id) {
            throw ValidationException::withMessages([
                'waitlist_entry_id' => ['Waitlist entry service does not match cancelled appointment service.'],
            ]);
        }

        if ($entry->preferred_staff_id !== null && $entry->preferred_staff_id !== $appointment->staff_id) {
            throw ValidationException::withMessages([
                'waitlist_entry_id' => ['Waitlist entry preferred staff does not match cancelled appointment staff.'],
            ]);
        }

        return $this->convertAction->handle(
            $entry,
            $appointment->starts_at->toIso8601String(),
            $appointment->staff_id,
            $notes,
        );
    }
}
