<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

final class ClientAntiNoShowService
{
    public function syncNoShowTransition(Client $client, string $previousStatus, string $newStatus): void
    {
        if ($previousStatus === $newStatus) {
            return;
        }

        DB::transaction(function () use ($client, $previousStatus, $newStatus): void {
            $lockedClient = Client::withoutTenantScope()
                ->whereKey($client->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($previousStatus !== AppointmentStatus::NoShow->value && $newStatus === AppointmentStatus::NoShow->value) {
                $lockedClient->update([
                    'no_show_count' => $lockedClient->no_show_count + 1,
                    'last_no_show_at' => now(),
                ]);

                return;
            }

            if ($previousStatus === AppointmentStatus::NoShow->value && $newStatus !== AppointmentStatus::NoShow->value) {
                $lockedClient->update([
                    'no_show_count' => max(0, $lockedClient->no_show_count - 1),
                ]);
            }
        });
    }

    public function trackLateCancellation(Appointment $appointment): void
    {
        $hoursUntilStart = now()->diffInHours($appointment->starts_at, false);
        $threshold = (int) config('reservation.no_show.late_cancellation_hours');

        if ($hoursUntilStart > $threshold) {
            return;
        }

        DB::transaction(function () use ($appointment): void {
            $client = Client::withoutTenantScope()
                ->whereKey($appointment->client_id)
                ->lockForUpdate()
                ->firstOrFail();

            $client->update([
                'late_cancellation_count' => $client->late_cancellation_count + 1,
                'last_late_cancellation_at' => now(),
            ]);
        });
    }
}
