<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Contracts\Repositories\AppointmentRepository;
use App\DTOs\Appointment\GetCalendarDayAppointmentsDTO;
use App\Http\Resources\AppointmentResource;
use Illuminate\Support\Facades\Log;

final class AppointmentCalendarDayGetAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(GetCalendarDayAppointmentsDTO $dto): array
    {
        $appointments = $this->appointmentRepository->findForDatePaginated(
            date: $dto->date,
            staffId: $dto->staffId,
            status: $dto->status,
            offset: $dto->offset,
            limit: $dto->limit,
            relations: $dto->relations,
        );

        $total = $this->appointmentRepository->countForDate(
            date: $dto->date,
            staffId: $dto->staffId,
            status: $dto->status,
        );

        $returned = $appointments->count();
        $nextOffset = $dto->offset + $returned;
        $hasMore = $returned > 0 && $nextOffset < $total;

        if ($returned === 0 && $dto->offset < $total) {
            Log::warning('Calendar day pagination mismatch for date.', [
                'date' => $dto->date->toDateString(),
                'offset' => $dto->offset,
                'total' => $total,
            ]);
        }

        return [
            'date' => $dto->date->toDateString(),
            'appointments' => AppointmentResource::collection($appointments)->resolve(),
            'pagination' => [
                'total' => $total,
                'offset' => $dto->offset,
                'limit' => $dto->limit,
                'returned' => $returned,
                'has_more' => $hasMore,
                'next_offset' => $returned > 0 ? $nextOffset : null,
            ],
        ];
    }
}
