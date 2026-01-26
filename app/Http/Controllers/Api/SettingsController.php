<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Settings\SettingsUpdateAction;
use App\Actions\Settings\SettingsWorkingHoursUpdateAction;
use App\Actions\Tenant\TenantLogoDeleteAction;
use App\Actions\Tenant\TenantLogoUploadAction;
use App\Contracts\Repositories\WorkingHoursRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Requests\Settings\UpdateWorkingHoursRequest;
use App\Http\Requests\Settings\UploadLogoRequest;
use App\Services\Tenant\TenantContextService;
use Illuminate\Http\JsonResponse;

final class SettingsController extends Controller
{
    public function __construct(
        private readonly TenantContextService $tenantContext,
        private readonly WorkingHoursRepository $workingHoursRepository,
        private readonly TenantLogoUploadAction $uploadLogoAction,
        private readonly TenantLogoDeleteAction $deleteLogoAction,
    ) {}

    public function index(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();
        $workingHours = $this->workingHoursRepository->getByTenantAndStaff($tenant->id, null);

        return response()->json([
            'tenant' => $tenant,
            'working_hours' => $workingHours,
        ]);
    }

    public function update(
        UpdateSettingsRequest $request,
        SettingsUpdateAction $action
    ): JsonResponse {
        $tenant = $this->tenantContext->getTenant();
        $updatedTenant = $action->handle($tenant, $request->toDTO());

        return response()->json(['tenant' => $updatedTenant]);
    }

    public function updateWorkingHours(
        UpdateWorkingHoursRequest $request,
        SettingsWorkingHoursUpdateAction $action
    ): JsonResponse {
        $tenant = $this->tenantContext->getTenant();
        $workingHours = $action->handle($tenant, $request->getWorkingHours());

        return response()->json(['working_hours' => $workingHours]);
    }

    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();
        $updatedTenant = $this->uploadLogoAction->handle($tenant, $request->getLogo());

        return response()->json(['tenant' => $updatedTenant]);
    }

    public function deleteLogo(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();
        $updatedTenant = $this->deleteLogoAction->handle($tenant);

        return response()->json(['tenant' => $updatedTenant]);
    }
}
