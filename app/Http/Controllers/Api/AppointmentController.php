<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Appointment\AppointmentCancelAction;
use App\Actions\Appointment\AppointmentCompleteAction;
use App\Actions\Appointment\AppointmentCreateAction;
use App\Actions\Appointment\AppointmentUpdateAction;
use App\Contracts\Repositories\AppointmentRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $date = $request->has('date') ? Carbon::parse($request->input('date')) : null;
        $startDate = $request->has('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->has('end_date') ? Carbon::parse($request->input('end_date')) : null;
        $staffId = $request->has('staff_id') ? (int) $request->input('staff_id') : null;
        $status = $request->input('status');

        $appointments = $this->appointmentRepository->findFiltered(
            date: $date,
            startDate: $startDate,
            endDate: $endDate,
            staffId: $staffId,
            status: $status,
            relations: ['client', 'service', 'staff']
        );

        return response()->json(['data' => $appointments]);
    }

    public function store(StoreAppointmentRequest $request, AppointmentCreateAction $action): JsonResponse
    {
        $appointment = $action->handle($request->toDTO());

        return response()->json(['data' => $appointment], 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $this->appointmentRepository->loadRelations($appointment, ['client', 'service', 'staff']);

        return response()->json(['data' => $appointment]);
    }

    public function update(
        UpdateAppointmentRequest $request,
        Appointment $appointment,
        AppointmentUpdateAction $action
    ): JsonResponse {
        $appointment = $action->handle($appointment, $request->toDTO());

        return response()->json(['data' => $appointment]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $this->appointmentRepository->delete($appointment);

        return response()->json(null, 204);
    }

    public function complete(Appointment $appointment, AppointmentCompleteAction $action): JsonResponse
    {
        $appointment = $action->handle($appointment);

        return response()->json(['data' => $appointment]);
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentCancelAction $action): JsonResponse
    {
        $appointment = $action->handle($appointment, $request->input('reason'));

        return response()->json(['data' => $appointment]);
    }
}
