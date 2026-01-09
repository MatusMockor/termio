<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BookingController extends Controller
{
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

        // Filter by service if provided
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

    public function availability(Request $request, string $tenantSlug): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();
        $date = Carbon::parse($validated['date']);
        $dayOfWeek = $date->dayOfWeek;
        $staffId = $validated['staff_id'] ?? null;

        $service = Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($validated['service_id']);

        // Check for all-day time off (staff-specific or business-wide)
        $allDayTimeOff = TimeOff::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->forDate($date)
            ->where(static function (Builder $query) use ($staffId): void {
                $query->whereNull('staff_id')
                    ->orWhere('staff_id', $staffId);
            })
            ->whereNull('start_time')
            ->whereNull('end_time')
            ->exists();

        if ($allDayTimeOff) {
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

        // Get partial time off for the date
        $timeOffPeriods = TimeOff::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->forDate($date)
            ->where(static function (Builder $query) use ($staffId): void {
                $query->whereNull('staff_id')
                    ->orWhere('staff_id', $staffId);
            })
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get();

        $slots = $this->generateAvailableSlots(
            $workingHours,
            $existingAppointments,
            $timeOffPeriods,
            $service->duration_minutes,
            $date
        );

        return response()->json(['slots' => $slots]);
    }

    public function create(Request $request, string $tenantSlug): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date', 'after:now'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['required', 'string', 'max:20'],
            'client_email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $service = Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($validated['service_id']);

        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);

        $client = Client::withoutTenantScope()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'phone' => $validated['client_phone'],
            ],
            [
                'name' => $validated['client_name'],
                'email' => $validated['client_email'] ?? null,
            ]
        );

        $appointment = Appointment::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'staff_id' => $validated['staff_id'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'source' => 'online',
        ]);

        return response()->json([
            'message' => 'Booking created successfully.',
            'appointment' => [
                'id' => $appointment->id,
                'service' => $service->name,
                'starts_at' => $appointment->starts_at,
                'ends_at' => $appointment->ends_at,
            ],
        ], 201);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Appointment>  $existingAppointments
     * @param  \Illuminate\Database\Eloquent\Collection<int, TimeOff>  $timeOffPeriods
     * @return array<array{time: string, available: bool}>
     */
    private function generateAvailableSlots(
        WorkingHours $workingHours,
        $existingAppointments,
        $timeOffPeriods,
        int $serviceDuration,
        Carbon $date
    ): array {
        $slots = [];
        $slotInterval = 30; // 30-minute intervals

        $startTime = Carbon::parse($date->format('Y-m-d').' '.$workingHours->start_time);
        $endTime = Carbon::parse($date->format('Y-m-d').' '.$workingHours->end_time);

        $current = $startTime->copy();

        while ($current->copy()->addMinutes($serviceDuration)->lte($endTime)) {
            $slotEnd = $current->copy()->addMinutes($serviceDuration);

            $isAvailable = $this->isSlotAvailable(
                $current,
                $slotEnd,
                $date,
                $existingAppointments,
                $timeOffPeriods
            );

            $slots[] = [
                'time' => $current->format('H:i'),
                'available' => $isAvailable,
            ];

            $current->addMinutes($slotInterval);
        }

        return $slots;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Appointment>  $appointments
     * @param  \Illuminate\Database\Eloquent\Collection<int, TimeOff>  $timeOffPeriods
     */
    private function isSlotAvailable(
        Carbon $slotStart,
        Carbon $slotEnd,
        Carbon $date,
        $appointments,
        $timeOffPeriods
    ): bool {
        // Check if slot is in the past
        if ($slotStart->lt(now())) {
            return false;
        }

        // Check for conflicting appointments
        foreach ($appointments as $appointment) {
            if ($slotStart->lt($appointment->ends_at) && $slotEnd->gt($appointment->starts_at)) {
                return false;
            }
        }

        // Check for time off periods
        foreach ($timeOffPeriods as $timeOff) {
            $timeOffStart = Carbon::parse($date->format('Y-m-d').' '.$timeOff->start_time);
            $timeOffEnd = Carbon::parse($date->format('Y-m-d').' '.$timeOff->end_time);

            if ($slotStart->lt($timeOffEnd) && $slotEnd->gt($timeOffStart)) {
                return false;
            }
        }

        return true;
    }
}
