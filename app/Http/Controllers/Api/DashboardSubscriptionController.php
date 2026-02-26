<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\GetDashboardSubscriptionContextAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardSubscriptionContextResource;
use App\Services\Tenant\TenantContextService;
use Illuminate\Http\JsonResponse;

final class DashboardSubscriptionController extends Controller
{
    public function __construct(
        private readonly TenantContextService $tenantContext,
    ) {}

    public function show(GetDashboardSubscriptionContextAction $action): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        return DashboardSubscriptionContextResource::make($action->handle($tenant))->response();
    }
}
