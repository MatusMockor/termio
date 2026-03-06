<?php

declare(strict_types=1);

namespace App\Actions\Waitlist;

use App\Contracts\Repositories\AppointmentRepository;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\WaitlistEntryStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\WaitlistEntry;
use App\Services\Appointment\AppointmentDurationService;
use App\Services\Appointment\AppointmentSlotValidationService;
use App\Services\Waitlist\WaitlistEntryValidationService;
use Illuminate\Validation\ValidationException;

final class WaitlistConvertToAppointmentAction
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentDurationService $durationService,
        private readonly AppointmentSlotValidationService $slotValidationService,
        private readonly WaitlistEntryValidationService $validationService,
    ) {}

    public function handle(
        WaitlistEntry $entry,
        string $startsAt,
        ?int $staffId = null,
        ?string $notes = null,
    ): Appointment {
        $this->ensureEntryConvertible($entry);

        $service = $entry->service;
        $times = $this->durationService->calculateTimesFromService($startsAt, $service);
        $tenant = $entry->tenant;
        $resolvedStaffId = $staffId ?? $entry->preferred_staff_id;

        $this->ensureSlotCanBeConverted($entry, $service, $tenant, $times, $resolvedStaffId);

        $appointment = $this->createAppointment($entry, $times, $resolvedStaffId, $notes);

        return $this->appointmentRepository->loadRelations($appointment, ['client', 'service', 'staff']);
    }

    /**
     * @param  array{starts_at: \Carbon\Carbon, ends_at: \Carbon\Carbon}  $times
     */
    private function ensureSlotCanBeConverted(
        WaitlistEntry $entry,
        \App\Models\Service $service,
        \App\Models\Tenant $tenant,
        array $times,
        ?int $resolvedStaffId,
    ): void {
        $this->validationService->ensureStaffSupportsService(
            $tenant,
            $entry->service_id,
            $resolvedStaffId,
            'staff_id',
        );

        $this->slotValidationService->ensureBookable(
            $tenant,
            $service,
            $times['starts_at'],
            $times['ends_at'],
            $resolvedStaffId,
        );
    }

    /**
     * @param  array{starts_at: \Carbon\Carbon, ends_at: \Carbon\Carbon}  $times
     */
    private function createAppointment(
        WaitlistEntry $entry,
        array $times,
        ?int $resolvedStaffId,
        ?string $notes,
    ): Appointment {
        return $entry->getConnection()->transaction(function () use ($entry, $resolvedStaffId, $times, $notes): Appointment {
            $client = $this->findOrCreateClient($entry);
            $appointment = $this->appointmentRepository->create([
                'tenant_id' => $entry->tenant_id,
                'client_id' => $client->id,
                'service_id' => $entry->service_id,
                'staff_id' => $resolvedStaffId,
                'starts_at' => $times['starts_at'],
                'ends_at' => $times['ends_at'],
                'notes' => $this->buildAppointmentNotes($entry, $notes),
                'status' => AppointmentStatus::Confirmed->value,
                'source' => AppointmentSource::Manual->value,
                'service_price_snapshot' => $entry->service->price,
                'voucher_discount_amount' => 0,
                'final_amount_due' => $entry->service->price,
            ]);

            $entry->update([
                'status' => WaitlistEntryStatus::Converted->value,
                'converted_appointment_id' => $appointment->id,
            ]);

            return $appointment;
        });
    }

    private function findOrCreateClient(WaitlistEntry $entry): Client
    {
        return Client::withoutTenantScope()->firstOrCreate(
            [
                'tenant_id' => $entry->tenant_id,
                'phone' => $entry->client_phone,
            ],
            [
                'name' => $entry->client_name,
                'email' => $entry->client_email,
            ],
        );
    }

    private function buildAppointmentNotes(WaitlistEntry $entry, ?string $notes): ?string
    {
        $entryNotes = $entry->notes ?? '';
        $conversionNotes = $notes ?? '';
        $combinedNotes = trim($entryNotes."\n".$conversionNotes);

        return $combinedNotes !== '' ? $combinedNotes : null;
    }

    private function ensureEntryConvertible(WaitlistEntry $entry): void
    {
        if ($entry->status === WaitlistEntryStatus::Converted) {
            throw ValidationException::withMessages([
                'waitlist_entry_id' => ['Waitlist entry was already converted.'],
            ]);
        }

        if ($entry->status === WaitlistEntryStatus::Cancelled) {
            throw ValidationException::withMessages([
                'waitlist_entry_id' => ['Cancelled waitlist entry cannot be converted.'],
            ]);
        }
    }
}
