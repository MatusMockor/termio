<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Contracts\Repositories\AppointmentRepository;
use App\Models\Appointment;

final class AppointmentCompleteAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
    ) {}

    public function handle(Appointment $appointment): Appointment
    {
        $this->appointmentRepository->update($appointment, ['status' => 'completed']);

        $client = $appointment->client;
        $client->incrementVisit((float) $appointment->service->price);

        return $appointment->fresh(['client', 'service', 'staff']);
    }
}
