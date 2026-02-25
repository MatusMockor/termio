<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Contracts\Repositories\AppointmentRepository;
use App\DTOs\Appointment\AppointmentIndexDTO;
use App\Models\Appointment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AppointmentIndexGetAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function handle(AppointmentIndexDTO $dto): LengthAwarePaginator
    {
        return $this->appointmentRepository->findFiltered(
            date: $dto->date,
            startDate: $dto->startDate,
            endDate: $dto->endDate,
            staffId: $dto->staffId,
            status: $dto->status,
            perPage: $dto->perPage,
            relations: $dto->relations,
        );
    }
}
