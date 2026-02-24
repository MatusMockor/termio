<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Appointment\AppointmentCancelAction;
use App\Actions\Appointment\AppointmentCompleteAction;
use App\Actions\Appointment\AppointmentCreateAction;
use App\Actions\Appointment\AppointmentUpdateAction;
use App\Contracts\Repositories\AppointmentRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\CalendarAppointmentsRequest;
use App\Http\Requests\Appointment\CalendarDayAppointmentsRequest;
use App\Http\Requests\Appointment\IndexAppointmentsRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentCollection;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function calendar(CalendarAppointmentsRequest $request): JsonResponse
    {
        $appointments = $this->appointmentRepository->findCalendarByDateRange(
            startDate: $request->getStartDate(),
            endDate: $request->getEndDate(),
            staffId: $request->getStaffId(),
            status: $request->getStatus(),
            perDay: $request->getPerDay(),
            relations: ['client', 'service', 'staff'],
        );

        $countsByDate = $this->appointmentRepository->countCalendarByDateRange(
            startDate: $request->getStartDate(),
            endDate: $request->getEndDate(),
            staffId: $request->getStaffId(),
            status: $request->getStatus(),
        );

        $days = [];
        $grouped = $appointments->groupBy(static fn (Appointment $appointment): string => $appointment->starts_at->toDateString());

        foreach ($grouped as $dateKey => $dayAppointments) {
            $loaded = $dayAppointments->count();
            $total = $countsByDate[$dateKey] ?? $loaded;

            $days[$dateKey] = [
                'appointments' => AppointmentResource::collection($dayAppointments)->resolve(),
                'pagination' => [
                    'total' => $total,
                    'loaded' => $loaded,
                    'per_day' => $request->getPerDay(),
                    'has_more' => $total > $loaded,
                    'next_offset' => $loaded,
                ],
            ];
        }

        ksort($days);

        return response()->json([
            'data' => [
                'days' => $days,
            ],
            'meta' => [
                'start_date' => $request->getStartDate()->toDateString(),
                'end_date' => $request->getEndDate()->toDateString(),
                'per_day' => $request->getPerDay(),
            ],
        ]);
    }

    public function calendarDay(CalendarDayAppointmentsRequest $request): JsonResponse
    {
        $appointments = $this->appointmentRepository->findForDatePaginated(
            date: $request->getDate(),
            staffId: $request->getStaffId(),
            status: $request->getStatus(),
            offset: $request->getOffset(),
            limit: $request->getLimit(),
            relations: ['client', 'service', 'staff'],
        );

        $total = $this->appointmentRepository->countForDate(
            date: $request->getDate(),
            staffId: $request->getStaffId(),
            status: $request->getStatus(),
        );

        $returned = $appointments->count();
        $nextOffset = $request->getOffset() + $returned;

        return response()->json([
            'data' => [
                'date' => $request->getDate()->toDateString(),
                'appointments' => AppointmentResource::collection($appointments)->resolve(),
                'pagination' => [
                    'total' => $total,
                    'offset' => $request->getOffset(),
                    'limit' => $request->getLimit(),
                    'returned' => $returned,
                    'has_more' => $nextOffset < $total,
                    'next_offset' => $nextOffset,
                ],
            ],
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

    public function cancel(Request $request, Appointment $appointment, AppointmentCancelAction $action): AppointmentResource
    {
        $appointment = $action->handle($appointment, $request->input('reason'));

        return new AppointmentResource($appointment);
    }
}
