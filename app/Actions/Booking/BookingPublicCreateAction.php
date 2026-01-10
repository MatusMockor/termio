<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Contracts\Repositories\AppointmentRepository;
use App\Contracts\Repositories\ServiceRepository;
use App\DTOs\Booking\CreatePublicBookingDTO;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\Appointment\AppointmentDurationService;
use Illuminate\Support\Facades\DB;

final class BookingPublicCreateAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly AppointmentDurationService $durationService,
    ) {}

    public function handle(CreatePublicBookingDTO $dto, Tenant $tenant): Appointment
    {
        $service = $this->serviceRepository->findByIdWithoutTenantScope($dto->serviceId, $tenant->id);

        $times = $this->durationService->calculateTimesFromService($dto->startsAt, $service);

        return DB::transaction(function () use ($dto, $tenant, $service, $times): Appointment {
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

            return $this->appointmentRepository->loadRelations($appointment, ['service']);
        });
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
}
