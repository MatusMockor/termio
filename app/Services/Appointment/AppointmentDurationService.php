<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Models\Service;
use Carbon\Carbon;

final class AppointmentDurationService
{
    /**
     * @return array{starts_at: Carbon, ends_at: Carbon}
     */
    public function calculateTimes(string $startsAt, int $durationMinutes): array
    {
        $start = Carbon::parse($startsAt);
        $end = $start->copy()->addMinutes($durationMinutes);

        return [
            'starts_at' => $start,
            'ends_at' => $end,
        ];
    }

    /**
     * @return array{starts_at: Carbon, ends_at: Carbon}
     */
    public function calculateTimesFromService(string $startsAt, Service $service): array
    {
        return $this->calculateTimes($startsAt, $service->duration_minutes);
    }
}
