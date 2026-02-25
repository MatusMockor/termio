<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Contracts\Repositories\AppointmentRepository;
use App\DTOs\Appointment\GetCalendarAppointmentsDTO;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

final class AppointmentCalendarGetAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(GetCalendarAppointmentsDTO $dto): array
    {
        $appointments = $this->appointmentRepository->findCalendarByDateRange(
            startDate: $dto->startDate,
            endDate: $dto->endDate,
            staffId: $dto->staffId,
            status: $dto->status,
            perDay: $dto->perDay,
            relations: $dto->relations,
        );

        $countsByDate = $this->appointmentRepository->countCalendarByDateRange(
            startDate: $dto->startDate,
            endDate: $dto->endDate,
            staffId: $dto->staffId,
            status: $dto->status,
        );

        $days = [];
        $grouped = $appointments->groupBy(static fn (Appointment $appointment): string => $appointment->starts_at->toDateString());

        foreach ($grouped as $dateKey => $dayAppointments) {
            $loaded = $dayAppointments->count();
            $hasCount = array_key_exists($dateKey, $countsByDate);
            $total = $hasCount ? $countsByDate[$dateKey] : $loaded;

            if (! $hasCount) {
                Log::warning('Calendar counts mismatch for date.', [
                    'date' => $dateKey,
                    'loaded' => $loaded,
                ]);
            }

            $hasMore = $hasCount ? $total > $loaded : true;

            $days[$dateKey] = [
                'appointments' => AppointmentResource::collection($dayAppointments)->resolve(),
                'pagination' => [
                    'total' => $total,
                    'loaded' => $loaded,
                    'per_day' => $dto->perDay,
                    'has_more' => $hasMore,
                    'next_offset' => $loaded,
                ],
            ];
        }

        ksort($days);

        return [
            'days' => $days,
        ];
    }
}
