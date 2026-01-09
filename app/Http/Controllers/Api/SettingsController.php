<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Requests\Settings\UpdateWorkingHoursRequest;
use App\Models\WorkingHours;
use App\Services\Tenant\TenantContextService;
use Illuminate\Http\JsonResponse;

final class SettingsController extends Controller
{
    public function __construct(
        private readonly TenantContextService $tenantContext
    ) {}

    public function index(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();
        $workingHours = WorkingHours::where('staff_id', null)->get();

        return response()->json([
            'tenant' => $tenant,
            'working_hours' => $workingHours,
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $data = array_filter([
            'name' => $request->getName(),
            'business_type' => $request->getBusinessType(),
            'address' => $request->getAddress(),
            'phone' => $request->getPhone(),
            'timezone' => $request->getTimezone(),
            'settings' => $request->getSettings(),
        ], static fn (mixed $value): bool => $value !== null);

        $tenant = $this->tenantContext->getTenant();
        $tenant->update($data);

        return response()->json(['tenant' => $tenant]);
    }

    public function updateWorkingHours(UpdateWorkingHoursRequest $request): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        WorkingHours::where('tenant_id', $tenant->id)
            ->where('staff_id', null)
            ->delete();

        foreach ($request->getWorkingHours() as $hours) {
            WorkingHours::create([
                'tenant_id' => $tenant->id,
                'staff_id' => null,
                'day_of_week' => $hours['day_of_week'],
                'start_time' => $hours['start_time'],
                'end_time' => $hours['end_time'],
                'is_active' => $hours['is_active'] ?? true,
            ]);
        }

        $workingHours = WorkingHours::where('tenant_id', $tenant->id)
            ->where('staff_id', null)
            ->get();

        return response()->json(['working_hours' => $workingHours]);
    }
}
