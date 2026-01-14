<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Contracts\Repositories\AppointmentRepository;
use App\Contracts\Repositories\ServiceRepository;
use App\DTOs\Appointment\CreateAppointmentDTO;
use App\Models\Appointment;
use App\Notifications\BookingConfirmed;
use App\Services\Appointment\AppointmentDurationService;

final class AppointmentCreateAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly AppointmentDurationService $durationService,
    ) {}

    public function handle(CreateAppointmentDTO $dto): Appointment
    {
        $service = $this->serviceRepository->findOrFail($dto->serviceId);
        $times = $this->durationService->calculateTimesFromService($dto->startsAt, $service);

        $appointment = $this->appointmentRepository->create([
            'client_id' => $dto->clientId,
            'service_id' => $dto->serviceId,
            'staff_id' => $dto->staffId,
            'starts_at' => $times['starts_at'],
            'ends_at' => $times['ends_at'],
            'notes' => $dto->notes,
            'status' => $dto->status,
            'source' => $dto->source,
        ]);

        $appointment = $this->appointmentRepository->loadRelations($appointment, ['client', 'service', 'staff', 'tenant']);

        $this->sendConfirmationEmail($appointment);

        return $appointment;
    }

    private function sendConfirmationEmail(Appointment $appointment): void
    {
        $client = $appointment->client;

        if (! $client->email) {
            return;
        }

        $client->notify(new BookingConfirmed($appointment));
    }
}
