<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SyncAppointmentToGoogleCalendar implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Appointment $appointment,
        public string $action
    ) {}

    public function handle(GoogleCalendarService $calendarService): void
    {
        // Find user with Google Calendar connected
        // First try staff assigned to appointment, then fall back to tenant owner
        $user = $this->findUserWithCalendar();

        if (! $user) {
            Log::debug('No user with Google Calendar found for appointment sync', [
                'appointment_id' => $this->appointment->id,
            ]);

            return;
        }

        match ($this->action) {
            'create' => $this->createEvent($calendarService, $user),
            'update' => $this->updateEvent($calendarService, $user),
            'delete' => $this->deleteEvent($calendarService, $user),
            default => null,
        };
    }

    private function findUserWithCalendar(): ?User
    {
        // First, try the staff member assigned to the appointment
        if ($this->appointment->staff_id) {
            $staffProfile = $this->appointment->staff;
            $staffUser = $staffProfile?->user;
            if ($staffUser?->hasGoogleCalendarConnected()) {
                return $staffUser;
            }
        }

        // Fall back to tenant owner
        $owner = User::where('tenant_id', $this->appointment->tenant_id)
            ->where('role', 'owner')
            ->whereNotNull('google_refresh_token')
            ->first();

        return $owner;
    }

    private function createEvent(GoogleCalendarService $calendarService, User $user): void
    {
        $eventId = $calendarService->createEvent($user, $this->appointment);

        if ($eventId) {
            $this->appointment->updateQuietly(['google_event_id' => $eventId]);

            Log::info('Created Google Calendar event for appointment', [
                'appointment_id' => $this->appointment->id,
                'google_event_id' => $eventId,
            ]);
        }
    }

    private function updateEvent(GoogleCalendarService $calendarService, User $user): void
    {
        if (! $this->appointment->google_event_id) {
            // If no event exists yet, create one
            $this->createEvent($calendarService, $user);

            return;
        }

        $success = $calendarService->updateEvent($user, $this->appointment);

        Log::info('Updated Google Calendar event for appointment', [
            'appointment_id' => $this->appointment->id,
            'google_event_id' => $this->appointment->google_event_id,
            'success' => $success,
        ]);
    }

    private function deleteEvent(GoogleCalendarService $calendarService, User $user): void
    {
        if (! $this->appointment->google_event_id) {
            return;
        }

        $eventId = $this->appointment->google_event_id;
        $success = $calendarService->deleteEvent($user, $eventId);

        if ($success) {
            $this->appointment->updateQuietly(['google_event_id' => null]);

            Log::info('Deleted Google Calendar event for appointment', [
                'appointment_id' => $this->appointment->id,
                'google_event_id' => $eventId,
            ]);
        }
    }
}
