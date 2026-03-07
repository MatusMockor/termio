<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Contracts\Repositories\AppointmentRepository;
use App\Contracts\Repositories\ServiceRepository;
use App\DTOs\Appointment\UpdateAppointmentDTO;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Appointment\AppointmentDurationService;
use App\Services\Client\ClientAntiNoShowService;

final class AppointmentUpdateAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly AppointmentDurationService $durationService,
        private readonly ClientAntiNoShowService $antiNoShowService,
    ) {}

    public function handle(Appointment $appointment, UpdateAppointmentDTO $dto): Appointment
    {
        $previousClient = $appointment->client;
        $previousStatus = $appointment->status;
        $data = array_filter([
            'client_id' => $dto->clientId,
            'service_id' => $dto->serviceId,
            'staff_id' => $dto->staffId,
            'starts_at' => $dto->startsAt,
            'notes' => $dto->notes,
            'status' => $dto->status,
        ], static fn (mixed $value): bool => $value !== null);

        if ($dto->hasStartsAt || $dto->hasServiceId) {
            $serviceId = $dto->serviceId ?? $appointment->service_id;
            $service = $this->serviceRepository->findOrFail($serviceId);
            $startsAt = $dto->startsAt ?? $appointment->starts_at->toIso8601String();
            $times = $this->durationService->calculateTimesFromService($startsAt, $service);
            $data['ends_at'] = $times['ends_at'];
        }

        $this->appointmentRepository->update($appointment, $data);

        if ($dto->clientId !== null) {
            $appointment->load('client');
        }

        if ($dto->status !== null) {
            $client = $previousStatus === AppointmentStatus::NoShow->value
                ? $previousClient
                : $appointment->client;

            $this->antiNoShowService->syncNoShowTransition($client, $previousStatus, $appointment->status);
        }

        return $this->appointmentRepository->loadRelations($appointment, ['client', 'service', 'staff']);
    }
}
