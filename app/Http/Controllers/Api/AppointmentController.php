<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Appointment\AppointmentCancelAction;
use App\Actions\Appointment\AppointmentCompleteAction;
use App\Actions\Appointment\AppointmentCreateAction;
use App\Actions\Appointment\AppointmentUpdateAction;
use App\Actions\Appointment\GetCalendarAppointmentsAction;
use App\Actions\Appointment\GetCalendarDayAppointmentsAction;
use App\Contracts\Repositories\AppointmentRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\CalendarAppointmentsRequest;
use App\Http\Requests\Appointment\CalendarDayAppointmentsRequest;
use App\Http\Requests\Appointment\CancelAppointmentRequest;
use App\Http\Requests\Appointment\IndexAppointmentsRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentCollection;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
    ) {}

    public function index(IndexAppointmentsRequest $request): AppointmentCollection
    {
        $appointments = $this->appointmentRepository->findFiltered(
            date: $request->getDate(),
            startDate: $request->getStartDate(),
            endDate: $request->getEndDate(),
            staffId: $request->getStaffId(),
            status: $request->getStatus(),
            perPage: $request->getPerPage(),
            relations: ['client', 'service', 'staff']
        );

        return new AppointmentCollection($appointments);
    }

    public function calendar(
        CalendarAppointmentsRequest $request,
        GetCalendarAppointmentsAction $action
    ): JsonResponse {
        $calendar = $action->handle($request->toDTO());

        return response()->json([
            'data' => $calendar,
            'meta' => [
                'start_date' => $request->getStartDate()->toDateString(),
                'end_date' => $request->getEndDate()->toDateString(),
                'per_day' => $request->getPerDay(),
            ],
        ]);
    }

    public function calendarDay(
        CalendarDayAppointmentsRequest $request,
        GetCalendarDayAppointmentsAction $action
    ): JsonResponse {
        $dayPayload = $action->handle($request->toDTO());

        return response()->json([
            'data' => $dayPayload,
        ]);
    }

    public function store(StoreAppointmentRequest $request, AppointmentCreateAction $action): JsonResponse
    {
        $appointment = $action->handle($request->toDTO());

        return AppointmentResource::make($appointment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('appointments.show', $appointment));
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        $this->appointmentRepository->loadRelations($appointment, ['client', 'service', 'staff']);

        return new AppointmentResource($appointment);
    }

    public function update(
        UpdateAppointmentRequest $request,
        Appointment $appointment,
        AppointmentUpdateAction $action
    ): AppointmentResource {
        $appointment = $action->handle($appointment, $request->toDTO());

        return new AppointmentResource($appointment);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $this->appointmentRepository->delete($appointment);

        return response()->json(null, 204);
    }

    public function complete(Appointment $appointment, AppointmentCompleteAction $action): AppointmentResource
    {
        $appointment = $action->handle($appointment);

        return new AppointmentResource($appointment);
    }

    public function cancel(
        CancelAppointmentRequest $request,
        Appointment $appointment,
        AppointmentCancelAction $action
    ): AppointmentResource {
        $appointment = $action->handle($appointment, $request->getReason());

        return new AppointmentResource($appointment);
    }
}
