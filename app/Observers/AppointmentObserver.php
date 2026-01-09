<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;

final class AppointmentObserver
{
    public function created(Appointment $appointment): void
    {
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
        if ($appointment->google_event_id) {
            SyncAppointmentToGoogleCalendar::dispatch($appointment, 'delete');
        }
    }
}
