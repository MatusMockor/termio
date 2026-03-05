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
use App\Services\Booking\Fields\BookingFieldResolverService;
use App\Services\Booking\Fields\BookingFieldValidationService;
use App\Services\Voucher\VoucherLedgerService;
use App\Services\Voucher\VoucherValidationService;
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
        private readonly BookingFieldResolverService $bookingFieldResolver,
        private readonly BookingFieldValidationService $bookingFieldValidation,
        private readonly VoucherValidationService $voucherValidationService,
        private readonly VoucherLedgerService $voucherLedgerService,
    ) {}

    public function handle(CreatePublicBookingDTO $dto, Tenant $tenant): Appointment
    {
        $service = $this->serviceRepository->findByIdWithoutTenantScope($dto->serviceId, $tenant->id);

        $times = $this->durationService->calculateTimesFromService($dto->startsAt, $service);
        $this->ensureWithinReservationWindow($tenant, $times['starts_at']);
        $this->ensureWithinBusinessWorkingHours($tenant, $times['starts_at'], $times['ends_at']);
        $effectiveFields = $this->bookingFieldResolver->resolveForService($tenant, $service);
        $this->bookingFieldValidation->validate($dto->customFields, $effectiveFields);

        $appointment = DB::transaction(function () use ($dto, $tenant, $service, $times): Appointment {
            $client = $this->findOrCreateClient($dto, $tenant);
            $servicePrice = (float) $service->price;
            $voucher = null;

            if ($dto->voucherCode !== null) {
                $voucher = $this->voucherValidationService->findRedeemableVoucher($tenant, $dto->voucherCode);
            }

            $appointment = $this->appointmentRepository->create([
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'service_id' => $service->id,
                'staff_id' => $dto->staffId,
                'starts_at' => $times['starts_at'],
                'ends_at' => $times['ends_at'],
                'notes' => $dto->notes,
                'custom_fields' => $dto->customFields !== [] ? $dto->customFields : null,
                'service_price_snapshot' => $servicePrice,
                'voucher_discount_amount' => 0,
                'final_amount_due' => $servicePrice,
                'status' => 'pending',
                'source' => 'online',
            ]);

            if ($voucher !== null) {
                $discountAmount = $this->voucherLedgerService->redeemForAppointment(
                    $voucher,
                    $appointment,
                    $servicePrice,
                );

                $appointment = $this->appointmentRepository->update($appointment, [
                    'voucher_id' => $voucher->id,
                    'voucher_discount_amount' => $discountAmount,
                    'final_amount_due' => round(max(0, $servicePrice - $discountAmount), 2),
                ]);
            }

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

        $businessWorkingHours = null;

        if ($hasConfiguredBusinessHours) {
            $activeBusinessHours = $this->workingHoursBusiness->getActiveBusinessHours($tenant->id);
            $businessWorkingHours = $this->workingHoursBusiness
                ->getBusinessHoursForDay($activeBusinessHours, $startsAt->dayOfWeek);
        }

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

    private function ensureWithinReservationWindow(Tenant $tenant, Carbon $startsAt): void
    {
        $now = now();
        $minimumAllowedStartAt = $now->copy()->addHours($tenant->getReservationLeadTimeHours());

        if ($startsAt->lt($minimumAllowedStartAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Selected time is too soon. Please choose a later time.',
            ]);
        }

        $latestAllowedStartAt = $now->copy()->startOfDay()
            ->addDays($tenant->getReservationMaxDaysInAdvance())
            ->endOfDay();

        if ($startsAt->lte($latestAllowedStartAt)) {
            return;
        }

        throw ValidationException::withMessages([
            'starts_at' => 'Selected time is too far in the future.',
        ]);
    }
}
