<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\StoreTimeOffRequest;
use App\Http\Requests\TimeOff\UpdateTimeOffRequest;
use App\Models\TimeOff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TimeOffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TimeOff::with('staff');

        if ($request->has('staff_id')) {
            $query->forStaff((int) $request->input('staff_id'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [
                Carbon::parse($request->input('start_date')),
                Carbon::parse($request->input('end_date')),
            ]);
        }

        if ($request->has('date')) {
            $query->forDate(Carbon::parse($request->input('date')));
        }

        $timeOffs = $query->orderBy('date')->get();

        return response()->json(['data' => $timeOffs]);
    }

    public function store(StoreTimeOffRequest $request): JsonResponse
    {
        $timeOff = TimeOff::create([
            'staff_id' => $request->getStaffId(),
            'date' => $request->getDate(),
            'start_time' => $request->getStartTime(),
            'end_time' => $request->getEndTime(),
            'reason' => $request->getReason(),
        ]);

        $timeOff->load('staff');

        return response()->json(['data' => $timeOff], 201);
    }

    public function show(TimeOff $timeOff): JsonResponse
    {
        $timeOff->load('staff');

        return response()->json(['data' => $timeOff]);
    }

    public function update(UpdateTimeOffRequest $request, TimeOff $timeOff): JsonResponse
    {
        $timeOff->update(array_filter([
            'staff_id' => $request->getStaffId(),
            'date' => $request->getDate(),
            'start_time' => $request->getStartTime(),
            'end_time' => $request->getEndTime(),
            'reason' => $request->getReason(),
        ], static fn (mixed $value): bool => $value !== null));

        $timeOff->load('staff');

        return response()->json(['data' => $timeOff]);
    }

    public function destroy(TimeOff $timeOff): JsonResponse
    {
        $timeOff->delete();

        return response()->json(null, 204);
    }
}
