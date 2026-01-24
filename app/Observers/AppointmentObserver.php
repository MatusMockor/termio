<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\Services\UsageLimitServiceContract;
use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;

final class AppointmentObserver
{
    public function __construct(
        private readonly UsageLimitServiceContract $usageLimitService,
    ) {}

    public function created(Appointment $appointment): void
    {
        // Track usage for subscription limits
        $this->usageLimitService->recordReservationCreated($appointment->tenant);

        // Sync to Google Calendar
        SyncAppointmentToGoogleCalendar::dispatch($appointment, 'create');
    }

    public function updated(Appointment $appointment): void
    {
        if (! $appointment->wasChanged(['starts_at', 'ends_at', 'status', 'service_id', 'client_id'])) {
            return;
        }

        if ($appointment->status === 'cancelled') {
            SyncAppointmentToGoogleCalendar::dispatch($appointment, 'delete');

            return;
        }

        SyncAppointmentToGoogleCalendar::dispatch($appointment, 'update');
    }

    public function deleted(Appointment $appointment): void
    {
        // Track usage for subscription limits
        $this->usageLimitService->recordReservationDeleted($appointment->tenant);

        // Sync to Google Calendar
        if ($appointment->google_event_id) {
            SyncAppointmentToGoogleCalendar::dispatch($appointment, 'delete');
        }
    }
}
