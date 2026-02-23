<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Contracts\Repositories\AppointmentRepository;
use App\Contracts\Repositories\ServiceRepository;
use App\Contracts\Services\WorkingHoursBusiness;
use App\DTOs\Booking\CreatePublicBookingDTO;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Tenant;
use App\Notifications\BookingConfirmed;
use App\Notifications\NewBookingReceived;
use App\Services\Appointment\AppointmentDurationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BookingPublicCreateAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly AppointmentDurationService $durationService,
        private readonly WorkingHoursBusiness $workingHoursBusiness,
    ) {}

    public function handle(CreatePublicBookingDTO $dto, Tenant $tenant): Appointment
    {
        $service = $this->serviceRepository->findByIdWithoutTenantScope($dto->serviceId, $tenant->id);

        $times = $this->durationService->calculateTimesFromService($dto->startsAt, $service);
        $this->ensureWithinBusinessWorkingHours($tenant, $times['starts_at'], $times['ends_at']);

        $appointment = DB::transaction(function () use ($dto, $tenant, $service, $times): Appointment {
            $client = $this->findOrCreateClient($dto, $tenant);

            $appointment = $this->appointmentRepository->create([
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'service_id' => $service->id,
                'staff_id' => $dto->staffId,
                'starts_at' => $times['starts_at'],
                'ends_at' => $times['ends_at'],
                'notes' => $dto->notes,
                'status' => 'pending',
                'source' => 'online',
            ]);

            return $this->appointmentRepository->loadRelations($appointment, ['client', 'service', 'staff', 'tenant']);
        });

        $this->sendNotifications($appointment);

        return $appointment;
    }

    private function sendNotifications(Appointment $appointment): void
    {
        $this->sendClientConfirmation($appointment);
        $this->sendTenantNotification($appointment);
    }

    private function sendClientConfirmation(Appointment $appointment): void
    {
        $client = $appointment->client;

        if (! $client->email) {
            return;
        }

        $client->notify(new BookingConfirmed($appointment));
    }

    private function sendTenantNotification(Appointment $appointment): void
    {
        $owner = $appointment->tenant->owner;

        if (! $owner) {
            return;
        }

        $owner->notify(new NewBookingReceived($appointment));
    }

    private function findOrCreateClient(CreatePublicBookingDTO $dto, Tenant $tenant): Client
    {
        return Client::withoutTenantScope()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'phone' => $dto->clientPhone,
            ],
            [
                'name' => $dto->clientName,
                'email' => $dto->clientEmail,
            ],
        );
    }

    private function ensureWithinBusinessWorkingHours(Tenant $tenant, Carbon $startsAt, Carbon $endsAt): void
    {
        $hasConfiguredBusinessHours = $this->workingHoursBusiness->hasConfiguredBusinessHours($tenant->id);
        $activeBusinessHours = $this->workingHoursBusiness->getActiveBusinessHours($tenant->id);
        $businessWorkingHours = $this->workingHoursBusiness
            ->getBusinessHoursForDay($activeBusinessHours, $startsAt->dayOfWeek);

        if ($this->workingHoursBusiness->isIntervalWithinBusinessHours(
            $startsAt,
            $endsAt,
            $businessWorkingHours,
            $hasConfiguredBusinessHours,
        )) {
            return;
        }

        throw ValidationException::withMessages([
            'starts_at' => 'Selected time is outside business opening hours.',
        ]);
    }
}
