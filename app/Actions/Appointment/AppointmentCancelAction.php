<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Contracts\Repositories\AppointmentRepository;
use App\Models\Appointment;

final class AppointmentCancelAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
    ) {}

    public function handle(Appointment $appointment, ?string $reason = null): Appointment
    {
        $cancelReason = $reason ?? 'No reason provided';
        $notes = $appointment->notes."\n[Cancelled: ".$cancelReason.']';

        $this->appointmentRepository->update($appointment, [
            'status' => 'cancelled',
            'notes' => $notes,
        ]);

        return $appointment->fresh(['client', 'service', 'staff']);
    }
}
