<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Staff\StaffCreateAction;
use App\Actions\Staff\StaffReorderAction;
use App\Actions\Staff\StaffUpdateAction;
use App\Actions\Staff\StaffWorkingHoursUpdateAction;
use App\Contracts\Repositories\StaffRepository;
use App\Contracts\Repositories\WorkingHoursRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ReorderStaffRequest;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Requests\Staff\UpdateStaffWorkingHoursRequest;
use App\Models\StaffProfile;
use Illuminate\Http\JsonResponse;

final class StaffController extends Controller
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
        private readonly WorkingHoursRepository $workingHoursRepository,
    ) {}

    public function index(): JsonResponse
    {
        $staff = $this->staffRepository->getAllOrdered();

        return response()->json(['data' => $staff]);
    }

    public function store(StoreStaffRequest $request, StaffCreateAction $action): JsonResponse
    {
        $staff = $action->handle($request->toDTO());

        return response()->json(['data' => $staff], 201);
    }

    public function show(StaffProfile $staff): JsonResponse
    {
        $staff->load('services:id,name');

        return response()->json(['data' => $staff]);
    }

    public function update(
        UpdateStaffRequest $request,
        StaffProfile $staff,
        StaffUpdateAction $action,
    ): JsonResponse {
        $staff = $action->handle($staff, $request->toDTO());

        return response()->json(['data' => $staff]);
    }

    public function destroy(StaffProfile $staff): JsonResponse
    {
        $this->staffRepository->delete($staff);

        return response()->json(null, 204);
    }

    public function reorder(ReorderStaffRequest $request, StaffReorderAction $action): JsonResponse
    {
        $action->handle($request->getOrder());

        return response()->json(['message' => 'Staff reordered successfully.']);
    }

    public function getWorkingHours(StaffProfile $staff): JsonResponse
    {
        $workingHours = $this->workingHoursRepository->getByStaffIdOrdered($staff->id);

        return response()->json(['data' => $workingHours]);
    }

    public function updateWorkingHours(
        UpdateStaffWorkingHoursRequest $request,
        StaffProfile $staff,
        StaffWorkingHoursUpdateAction $action,
    ): JsonResponse {
        $workingHours = $action->handle($staff, $request->getWorkingHours());

        return response()->json(['data' => $workingHours]);
    }
}
