<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Contracts\Repositories\AppointmentRepository;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentCancelled;
use App\Services\Voucher\VoucherLedgerService;
use Illuminate\Support\Facades\DB;

final class AppointmentCancelAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly VoucherLedgerService $voucherLedgerService,
    ) {}

    public function handle(Appointment $appointment, ?string $reason = null): Appointment
    {
        $cancelReason = $reason ?? 'No reason provided';
        $notes = $appointment->notes."\n[Cancelled: ".$cancelReason.']';

        DB::transaction(function () use ($appointment, $notes): void {
            $updatedAppointment = $this->appointmentRepository->update($appointment, [
                'status' => AppointmentStatus::Cancelled->value,
                'notes' => $notes,
            ]);

            $this->voucherLedgerService->restoreForCancelledAppointment($updatedAppointment);
        });

        $appointment = $appointment->fresh(['client', 'service', 'staff', 'tenant']);

        $this->sendCancellationEmail($appointment);

        return $appointment;
    }

    private function sendCancellationEmail(Appointment $appointment): void
    {
        $client = $appointment->client;

        if (! $client->email) {
            return;
        }

        $client->notify(new AppointmentCancelled($appointment));
    }
}
