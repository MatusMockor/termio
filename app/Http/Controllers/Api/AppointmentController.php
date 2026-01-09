<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Appointment::with(['client', 'service', 'staff']);

        if ($request->has('date')) {
            $query->forDate(Carbon::parse($request->input('date')));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->forDateRange(
                Carbon::parse($request->input('start_date')),
                Carbon::parse($request->input('end_date'))
            );
        }

        if ($request->has('staff_id')) {
            $query->forStaff((int) $request->input('staff_id'));
        }

        if ($request->has('status')) {
            $query->withStatus($request->input('status'));
        }

        $appointments = $query->orderBy('starts_at')->get();

        return response()->json(['data' => $appointments]);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $service = Service::findOrFail($request->getServiceId());
        $startsAt = Carbon::parse($request->getStartsAt());
        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);

        $appointment = Appointment::create([
            'client_id' => $request->getClientId(),
            'service_id' => $request->getServiceId(),
            'staff_id' => $request->getStaffId(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'notes' => $request->getNotes(),
            'status' => $request->getStatus(),
            'source' => $request->getSource(),
        ]);

        $appointment->load(['client', 'service', 'staff']);

        return response()->json(['data' => $appointment], 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load(['client', 'service', 'staff']);

        return response()->json(['data' => $appointment]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $data = array_filter([
            'client_id' => $request->getClientId(),
            'service_id' => $request->getServiceId(),
            'staff_id' => $request->getStaffId(),
            'starts_at' => $request->getStartsAt(),
            'notes' => $request->getNotes(),
            'status' => $request->getStatus(),
        ], static fn (mixed $value): bool => $value !== null);

        if ($request->hasStartsAt() || $request->hasServiceId()) {
            $serviceId = $request->getServiceId() ?? $appointment->service_id;
            $service = Service::findOrFail($serviceId);
            $startsAt = Carbon::parse($request->getStartsAt() ?? $appointment->starts_at);
            $data['ends_at'] = $startsAt->copy()->addMinutes($service->duration_minutes);
        }

        $appointment->update($data);
        $appointment->load(['client', 'service', 'staff']);

        return response()->json(['data' => $appointment]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(null, 204);
    }

    public function complete(Appointment $appointment): JsonResponse
    {
        $appointment->update(['status' => 'completed']);

        $client = $appointment->client;
        $client->incrementVisit((float) $appointment->service->price);

        return response()->json(['data' => $appointment->fresh(['client', 'service', 'staff'])]);
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        $appointment->update([
            'status' => 'cancelled',
            'notes' => $appointment->notes."\n[Cancelled: ".($request->input('reason', 'No reason provided').']'),
        ]);

        return response()->json(['data' => $appointment->fresh(['client', 'service', 'staff'])]);
    }
}
