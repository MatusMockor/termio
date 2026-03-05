<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Booking\BookingPublicCreateAction;
use App\Contracts\Services\BookingAvailability;
use App\Contracts\Services\PublicBookingRead;
use App\Enums\WaitlistEntrySource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\PublicAvailabilityRequest;
use App\Http\Requests\Booking\PublicAvailableDatesRequest;
use App\Http\Requests\Booking\PublicCreateBookingRequest;
use App\Http\Requests\Voucher\PublicValidateVoucherRequest;
use App\Http\Requests\Waitlist\PublicStoreWaitlistEntryRequest;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\WaitlistEntry;
use App\Services\Booking\Fields\BookingFieldResolverService;
use App\Services\Voucher\VoucherValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingAvailability $bookingAvailabilityService,
        private readonly PublicBookingRead $bookingReadService,
        private readonly BookingPublicCreateAction $bookingCreateAction,
        private readonly BookingFieldResolverService $bookingFieldResolver,
        private readonly VoucherValidationService $voucherValidationService,
    ) {}

    public function tenantInfo(string $tenantSlug): JsonResponse
    {
        return response()->json($this->bookingReadService->getTenantInfo($tenantSlug));
    }

    public function services(string $tenantSlug): JsonResponse
    {
        $services = $this->bookingReadService->getServices($tenantSlug);

        return response()->json(['data' => $services]);
    }

    public function staff(Request $request, string $tenantSlug): JsonResponse
    {
        $serviceId = $request->integer('service_id') ?: null;
        $staff = $this->bookingReadService->getStaff($tenantSlug, $serviceId);

        return response()->json(['data' => $staff]);
    }

    public function staffServices(string $tenantSlug, int $staffId): JsonResponse
    {
        $services = $this->bookingReadService->getStaffServices($tenantSlug, $staffId);

        return response()->json(['data' => $services]);
    }

    public function serviceBookingFields(string $tenantSlug, int $serviceId): JsonResponse
    {
        $tenant = $this->bookingReadService->getTenantBySlug($tenantSlug);
        $service = Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('id', $serviceId)
            ->firstOrFail();

        $fields = $this->bookingFieldResolver->resolveForService($tenant, $service);

        return response()->json([
            'data' => $fields,
        ]);
    }

    public function availability(PublicAvailabilityRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = $this->bookingReadService->getTenantBySlug($tenantSlug);
        $slots = $this->bookingAvailabilityService->getAvailability(
            $tenant,
            $request->getServiceId(),
            $request->getDate(),
            $request->getStaffId()
        );

        return response()->json(['slots' => $slots]);
    }

    public function availableDates(PublicAvailableDatesRequest $request, string $tenantSlug): JsonResponse
    {
        $availableDates = $this->bookingReadService->getAvailableDates(
            $tenantSlug,
            $request->getServiceId(),
            $request->getMonth(),
            $request->getYear(),
            $request->getStaffId()
        );

        return response()->json(['available_dates' => $availableDates]);
    }

    public function validateVoucher(PublicValidateVoucherRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = $this->bookingReadService->getTenantBySlug($tenantSlug);

        try {
            $payload = $this->voucherValidationService->validateForService(
                $tenant,
                $request->getCode(),
                $request->getServiceId(),
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'valid' => false,
                'discount_amount' => 0,
                'remaining_balance' => 0,
                'expires_at' => null,
            ], 200);
        }

        return response()->json($payload);
    }

    public function create(PublicCreateBookingRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = $this->bookingReadService->getTenantBySlug($tenantSlug);

        $appointment = $this->bookingCreateAction->handle($request->toDTO(), $tenant);
        $staff = $this->bookingReadService->getStaffSummary($appointment->staff_id);

        return response()->json([
            'appointment_id' => $appointment->id,
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'ends_at' => $appointment->ends_at->toIso8601String(),
            'service' => [
                'id' => $appointment->service->id,
                'name' => $appointment->service->name,
                'duration_minutes' => $appointment->service->duration_minutes,
                'price' => $appointment->service->price,
            ],
            'pricing' => [
                'service_price' => (float) $appointment->service_price_snapshot,
                'voucher_discount_amount' => (float) $appointment->voucher_discount_amount,
                'final_amount_due' => (float) $appointment->final_amount_due,
            ],
            'staff' => $staff,
            'client_name' => $request->getClientName(),
        ], 201);
    }

    public function waitlist(PublicStoreWaitlistEntryRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = $this->bookingReadService->getTenantBySlug($tenantSlug);
        $payload = $request->getWaitlistData();

        $serviceExists = Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('id', (int) $payload['service_id'])
            ->exists();

        if (! $serviceExists) {
            return response()->json([
                'message' => 'The selected service is invalid.',
                'errors' => [
                    'service_id' => ['The selected service is invalid.'],
                ],
            ], 422);
        }

        if (isset($payload['preferred_staff_id'])) {
            $staffExists = StaffProfile::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('id', (int) $payload['preferred_staff_id'])
                ->exists();

            if (! $staffExists) {
                return response()->json([
                    'message' => 'The selected staff is invalid.',
                    'errors' => [
                        'preferred_staff_id' => ['The selected staff is invalid.'],
                    ],
                ], 422);
            }
        }

        $entry = WaitlistEntry::create([
            ...$payload,
            'tenant_id' => $tenant->id,
            'source' => WaitlistEntrySource::Public->value,
        ])->refresh();

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'status' => $entry->status->value,
            ],
        ], 201);
    }
}
