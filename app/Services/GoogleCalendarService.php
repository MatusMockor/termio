<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleCalendarService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client;
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect').'/calendar/callback');
        $this->client->addScope(Calendar::CALENDAR_EVENTS);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function exchangeCode(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException('Failed to exchange code: '.$token['error_description']);
        }

        return $token;
    }

    public function setUserTokens(User $user): void
    {
        $expiresIn = 0;
        if ($user->google_token_expires_at !== null) {
            $expiresAt = Carbon::parse($user->google_token_expires_at);
            $expiresIn = (int) now()->diffInSeconds($expiresAt, false);
        }

        $this->client->setAccessToken([
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => $expiresIn,
        ]);

        if ($this->client->isAccessTokenExpired() && $user->google_refresh_token) {
            $newToken = $this->client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);

            $user->update([
                'google_access_token' => $newToken['access_token'],
                'google_token_expires_at' => now()->addSeconds($newToken['expires_in']),
            ]);

            $this->client->setAccessToken($newToken);
        }
    }

    public function createEvent(User $user, Appointment $appointment): ?string
    {
        try {
            $this->setUserTokens($user);
            $calendar = new Calendar($this->client);

            $event = new Event([
                'summary' => $this->getEventSummary($appointment),
                'description' => $this->getEventDescription($appointment),
                'start' => new EventDateTime([
                    'dateTime' => $appointment->starts_at->toRfc3339String(),
                    'timeZone' => $appointment->tenant->timezone ?? 'Europe/Bratislava',
                ]),
                'end' => new EventDateTime([
                    'dateTime' => $appointment->ends_at->toRfc3339String(),
                    'timeZone' => $appointment->tenant->timezone ?? 'Europe/Bratislava',
                ]),
            ]);

            $createdEvent = $calendar->events->insert('primary', $event);

            return $createdEvent->getId();
        } catch (\Exception $e) {
            Log::error('Failed to create Google Calendar event', [
                'appointment_id' => $appointment->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateEvent(User $user, Appointment $appointment): bool
    {
        if (! $appointment->google_event_id) {
            return false;
        }

        try {
            $this->setUserTokens($user);
            $calendar = new Calendar($this->client);

            $event = $calendar->events->get('primary', $appointment->google_event_id);

            $event->setSummary($this->getEventSummary($appointment));
            $event->setDescription($this->getEventDescription($appointment));
            $event->setStart(new EventDateTime([
                'dateTime' => $appointment->starts_at->toRfc3339String(),
                'timeZone' => $appointment->tenant->timezone ?? 'Europe/Bratislava',
            ]));
            $event->setEnd(new EventDateTime([
                'dateTime' => $appointment->ends_at->toRfc3339String(),
                'timeZone' => $appointment->tenant->timezone ?? 'Europe/Bratislava',
            ]));

            $calendar->events->update('primary', $appointment->google_event_id, $event);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update Google Calendar event', [
                'appointment_id' => $appointment->id,
                'google_event_id' => $appointment->google_event_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function deleteEvent(User $user, string $eventId): bool
    {
        try {
            $this->setUserTokens($user);
            $calendar = new Calendar($this->client);

            $calendar->events->delete('primary', $eventId);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete Google Calendar event', [
                'google_event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getEventSummary(Appointment $appointment): string
    {
        $clientName = $appointment->client->name ?? 'Klient';
        $serviceName = $appointment->service->name ?? 'Rezervácia';

        return "{$clientName} - {$serviceName}";
    }

    private function getEventDescription(Appointment $appointment): string
    {
        $lines = [];

        $lines[] = "Klient: {$appointment->client->name}";
        if ($appointment->client->phone) {
            $lines[] = "Telefón: {$appointment->client->phone}";
        }
        if ($appointment->client->email) {
            $lines[] = "Email: {$appointment->client->email}";
        }

        $lines[] = "Služba: {$appointment->service->name}";
        $lines[] = "Trvanie: {$appointment->service->duration_minutes} min";

        if ($appointment->notes) {
            $lines[] = "Poznámky: {$appointment->notes}";
        }

        $lines[] = '';
        $lines[] = 'Vytvorené cez Termio';

        return implode("\n", $lines);
    }
}
