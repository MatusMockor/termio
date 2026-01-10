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

        if ($this->hasAllDayTimeOff($tenant->id, $date, $staffId)) {
            return response()->json(['slots' => []]);
        }

        $workingHours = WorkingHours::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('staff_id', $staffId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (! $workingHours) {
            return response()->json(['slots' => []]);
        }

        $existingAppointments = Appointment::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->forDate($date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($staffId, static fn (Builder $q, int $id): Builder => $q->where('staff_id', $id))
            ->get();

        $timeOffPeriods = $this->getPartialTimeOffPeriods($tenant->id, $date, $staffId);

        $slots = $this->availabilitySlotService->generateAvailableSlots(
            $workingHours,
            $existingAppointments,
            $timeOffPeriods,
            $service->duration_minutes,
            $date,
        );

        return response()->json(['slots' => $slots]);
    }

    public function create(PublicCreateBookingRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $appointment = $this->bookingCreateAction->handle($request->toDTO(), $tenant);

        return response()->json([
            'message' => 'Booking created successfully.',
            'appointment' => [
                'id' => $appointment->id,
                'service' => $appointment->service->name,
                'starts_at' => $appointment->starts_at,
                'ends_at' => $appointment->ends_at,
            ],
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
