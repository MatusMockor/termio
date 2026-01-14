<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Booking\BookingPublicCreateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\PublicAvailabilityRequest;
use App\Http\Requests\Booking\PublicCreateBookingRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\WorkingHours;
use App\Services\Booking\AvailabilitySlotService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BookingController extends Controller
{
    public function __construct(
        private readonly AvailabilitySlotService $availabilitySlotService,
        private readonly BookingPublicCreateAction $bookingCreateAction,
    ) {}

    public function tenantInfo(string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        return response()->json([
            'name' => $tenant->name,
            'business_type' => $tenant->business_type,
            'address' => $tenant->address,
            'phone' => $tenant->phone,
        ]);
    }

    public function services(string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $services = Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->bookableOnline()
            ->ordered()
            ->get(['id', 'name', 'description', 'duration_minutes', 'price', 'category']);

        return response()->json(['data' => $services]);
    }

    public function staff(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $query = StaffProfile::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->bookable()
            ->ordered();

        if ($request->has('service_id')) {
            $query->forService((int) $request->input('service_id'));
        }

        $staff = $query->get(['id', 'display_name', 'bio', 'photo_url', 'specializations']);

        return response()->json(['data' => $staff]);
    }

    public function staffServices(string $tenantSlug, int $staffId): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $staffProfile = StaffProfile::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($staffId);

        $services = $staffProfile->services()
            ->active()
            ->bookableOnline()
            ->ordered()
            ->get(['services.id', 'name', 'description', 'duration_minutes', 'price', 'category']);

        return response()->json(['data' => $services]);
    }

    public function availability(PublicAvailabilityRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();
        $date = Carbon::parse($request->getDate());
        $dayOfWeek = $date->dayOfWeek;
        $staffId = $request->getStaffId();

        $service = Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($request->getServiceId());

        // If specific staff selected, use single staff flow
        if ($staffId) {
            return $this->getAvailabilityForStaff(
                $tenant->id,
                $staffId,
                $service,
                $date,
                $dayOfWeek
            );
        }

        // No staff selected - aggregate slots from all available staff
        return $this->getAvailabilityForAnyStaff(
            $tenant->id,
            $service,
            $date,
            $dayOfWeek
        );
    }

    private function getAvailabilityForStaff(
        int $tenantId,
        int $staffId,
        Service $service,
        Carbon $date,
        int $dayOfWeek
    ): JsonResponse {
        if ($this->hasAllDayTimeOff($tenantId, $date, $staffId)) {
            return response()->json(['slots' => []]);
        }

        $workingHours = WorkingHours::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('staff_id', $staffId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (! $workingHours) {
            return response()->json(['slots' => []]);
        }

        $existingAppointments = Appointment::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->forDate($date)
            ->where('staff_id', $staffId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get();

        $timeOffPeriods = $this->getPartialTimeOffPeriods($tenantId, $date, $staffId);

        $slots = $this->availabilitySlotService->generateAvailableSlots(
            $workingHours,
            $existingAppointments,
            $timeOffPeriods,
            $service->duration_minutes,
            $date,
        );

        return response()->json(['slots' => $slots]);
    }

    private function getAvailabilityForAnyStaff(
        int $tenantId,
        Service $service,
        Carbon $date,
        int $dayOfWeek
    ): JsonResponse {
        // Get all staff who can perform this service
        $staffIds = StaffProfile::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->bookable()
            ->forService($service->id)
            ->pluck('id')
            ->toArray();

        if (empty($staffIds)) {
            return response()->json(['slots' => []]);
        }

        $allSlots = [];

        foreach ($staffIds as $staffId) {
            if ($this->hasAllDayTimeOff($tenantId, $date, $staffId)) {
                continue;
            }

            $workingHours = WorkingHours::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('staff_id', $staffId)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->first();

            if (! $workingHours) {
                continue;
            }

            $existingAppointments = Appointment::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->forDate($date)
                ->where('staff_id', $staffId)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->get();

            $timeOffPeriods = $this->getPartialTimeOffPeriods($tenantId, $date, $staffId);

            $staffSlots = $this->availabilitySlotService->generateAvailableSlots(
                $workingHours,
                $existingAppointments,
                $timeOffPeriods,
                $service->duration_minutes,
                $date,
            );

            // Add staff_id to each available slot and merge
            foreach ($staffSlots as $slot) {
                if (! $slot['available']) {
                    continue;
                }

                $slotTime = $slot['time'];
                if (! isset($allSlots[$slotTime])) {
                    $allSlots[$slotTime] = [
                        'time' => $slotTime,
                        'available' => true,
                        'staff_id' => $staffId,
                    ];
                }
            }
        }

        // Sort by time and return as indexed array
        ksort($allSlots);

        return response()->json(['slots' => array_values($allSlots)]);
    }

    public function create(PublicCreateBookingRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $appointment = $this->bookingCreateAction->handle($request->toDTO(), $tenant);

        $staffProfile = $appointment->staff_id
            ? StaffProfile::withoutTenantScope()->find($appointment->staff_id)
            : null;

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
            'staff' => $staffProfile ? [
                'id' => $staffProfile->id,
                'display_name' => $staffProfile->display_name,
            ] : null,
            'client_name' => $request->getClientName(),
        ], 201);
    }

    private function hasAllDayTimeOff(int $tenantId, Carbon $date, ?int $staffId): bool
    {
        return TimeOff::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->forDate($date)
            ->where(static function (Builder $query) use ($staffId): void {
                $query->whereNull('staff_id')
                    ->orWhere('staff_id', $staffId);
            })
            ->whereNull('start_time')
            ->whereNull('end_time')
            ->exists();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TimeOff>
     */
    private function getPartialTimeOffPeriods(int $tenantId, Carbon $date, ?int $staffId): \Illuminate\Database\Eloquent\Collection
    {
        return TimeOff::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->forDate($date)
            ->where(static function (Builder $query) use ($staffId): void {
                $query->whereNull('staff_id')
                    ->orWhere('staff_id', $staffId);
            })
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get();
    }
}
