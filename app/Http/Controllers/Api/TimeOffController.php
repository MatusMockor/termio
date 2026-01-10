<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\TimeOff\TimeOffCreateAction;
use App\Actions\TimeOff\TimeOffUpdateAction;
use App\Contracts\Repositories\TimeOffRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\StoreTimeOffRequest;
use App\Http\Requests\TimeOff\UpdateTimeOffRequest;
use App\Models\TimeOff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TimeOffController extends Controller
{
    public function __construct(
        private readonly TimeOffRepository $timeOffRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($request->has('staff_id')) {
            $timeOffs = $this->timeOffRepository->getByStaff((int) $request->input('staff_id'));

            return response()->json(['data' => $timeOffs]);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $timeOffs = $this->timeOffRepository->getByDateRange(
                Carbon::parse($request->input('start_date')),
                Carbon::parse($request->input('end_date')),
            );

            return response()->json(['data' => $timeOffs]);
        }

        if ($request->has('date')) {
            $timeOffs = $this->timeOffRepository->getByDate(
                Carbon::parse($request->input('date')),
            );

            return response()->json(['data' => $timeOffs]);
        }

        $timeOffs = $this->timeOffRepository->getAllOrderedByDate();

        return response()->json(['data' => $timeOffs]);
    }

    public function store(StoreTimeOffRequest $request, TimeOffCreateAction $action): JsonResponse
    {
        $timeOff = $action->handle($request->toDTO());

        return response()->json(['data' => $timeOff], 201);
    }

    public function show(TimeOff $timeOff): JsonResponse
    {
        $timeOff->load('staff');

        return response()->json(['data' => $timeOff]);
    }

    public function update(
        UpdateTimeOffRequest $request,
        TimeOff $timeOff,
        TimeOffUpdateAction $action,
    ): JsonResponse {
        $timeOff = $action->handle($timeOff, $request->toDTO());

        return response()->json(['data' => $timeOff]);
    }

    public function destroy(TimeOff $timeOff): JsonResponse
    {
        $this->timeOffRepository->delete($timeOff);

        return response()->json(null, 204);
    }
}
