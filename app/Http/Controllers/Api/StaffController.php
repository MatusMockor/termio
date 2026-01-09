<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Models\WorkingHours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        $staff = StaffProfile::with('services:id,name')
            ->ordered()
            ->get();

        return response()->json(['data' => $staff]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'string', 'url', 'max:500'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:100'],
            'is_bookable' => ['boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        $maxOrder = StaffProfile::max('sort_order') ?? 0;
        $validated['sort_order'] = $maxOrder + 1;

        $serviceIds = $validated['service_ids'] ?? [];
        unset($validated['service_ids']);

        $staff = StaffProfile::create($validated);

        if (! empty($serviceIds)) {
            $staff->services()->sync($serviceIds);
        }

        $staff->load('services:id,name');

        return response()->json(['data' => $staff], 201);
    }

    public function show(StaffProfile $staff): JsonResponse
    {
        $staff->load('services:id,name');

        return response()->json(['data' => $staff]);
    }

    public function update(Request $request, StaffProfile $staff): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'string', 'url', 'max:500'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:100'],
            'is_bookable' => ['boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        $serviceIds = $validated['service_ids'] ?? null;
        unset($validated['service_ids']);

        $staff->update($validated);

        if ($serviceIds !== null) {
            $staff->services()->sync($serviceIds);
        }

        $staff->load('services:id,name');

        return response()->json(['data' => $staff]);
    }

    public function destroy(StaffProfile $staff): JsonResponse
    {
        $staff->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:staff_profiles,id'],
        ]);

        foreach ($validated['order'] as $position => $staffId) {
            StaffProfile::where('id', $staffId)->update(['sort_order' => $position]);
        }

        return response()->json(['message' => 'Staff reordered successfully.']);
    }

    public function getWorkingHours(StaffProfile $staff): JsonResponse
    {
        $workingHours = WorkingHours::where('staff_id', $staff->id)
            ->orderBy('day_of_week')
            ->get();

        return response()->json(['data' => $workingHours]);
    }

    public function updateWorkingHours(Request $request, StaffProfile $staff): JsonResponse
    {
        $validated = $request->validate([
            'working_hours' => ['required', 'array'],
            'working_hours.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'working_hours.*.start_time' => ['required', 'date_format:H:i'],
            'working_hours.*.end_time' => ['required', 'date_format:H:i', 'after:working_hours.*.start_time'],
            'working_hours.*.is_active' => ['boolean'],
        ]);

        // Delete existing working hours for this staff
        WorkingHours::where('staff_id', $staff->id)->delete();

        // Create new working hours
        foreach ($validated['working_hours'] as $hours) {
            WorkingHours::create([
                'tenant_id' => $staff->tenant_id,
                'staff_id' => $staff->id,
                'day_of_week' => $hours['day_of_week'],
                'start_time' => $hours['start_time'],
                'end_time' => $hours['end_time'],
                'is_active' => $hours['is_active'] ?? true,
            ]);
        }

        $workingHours = WorkingHours::where('staff_id', $staff->id)
            ->orderBy('day_of_week')
            ->get();

        return response()->json(['data' => $workingHours]);
    }
}
