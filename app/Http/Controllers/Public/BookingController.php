<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Booking\BookingPublicCreateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\PublicAvailabilityRequest;
use App\Http\Requests\Booking\PublicAvailableDatesRequest;
use App\Http\Requests\Booking\PublicCreateBookingRequest;
use App\Services\Booking\BookingAvailabilityService;
use App\Services\Booking\PublicBookingReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingAvailabilityService $bookingAvailabilityService,
        private readonly PublicBookingReadService $bookingReadService,
        private readonly BookingPublicCreateAction $bookingCreateAction,
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
        $serviceId = $request->has('service_id') ? (int) $request->input('service_id') : null;
        $staff = $this->bookingReadService->getStaff($tenantSlug, $serviceId);

        return response()->json(['data' => $staff]);
    }

    public function staffServices(string $tenantSlug, int $staffId): JsonResponse
    {
        $services = $this->bookingReadService->getStaffServices($tenantSlug, $staffId);

        return response()->json(['data' => $services]);
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
            'staff' => $staff,
            'client_name' => $request->getClientName(),
        ], 201);
    }
}
