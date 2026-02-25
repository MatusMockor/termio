<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Contracts\Repositories\AppointmentRepository;
use App\DTOs\Appointment\GetCalendarDayAppointmentsDTO;
use App\Http\Resources\AppointmentResource;

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

        return [
            'date' => $dto->date->toDateString(),
            'appointments' => AppointmentResource::collection($appointments)->resolve(),
            'pagination' => [
                'total' => $total,
                'offset' => $dto->offset,
                'limit' => $dto->limit,
                'returned' => $returned,
                'has_more' => $nextOffset < $total,
                'next_offset' => $nextOffset,
            ],
        ];
    }
}
