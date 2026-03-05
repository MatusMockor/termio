<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Service\UpdateServiceBookingFieldOverridesAction;
use App\Http\Requests\Service\UpdateServiceBookingFieldsRequest;
use App\Models\Service;
use App\Services\Booking\Fields\BookingFieldResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceBookingFieldOverrideController extends ApiController
{
    public function update(
        UpdateServiceBookingFieldsRequest $request,
        Service $service,
        BookingFieldResolverService $resolver,
        UpdateServiceBookingFieldOverridesAction $action,
    ): JsonResponse {
        $this->ensureTenantOwnership($request, $service->tenant_id);

        $effectiveFields = $action->execute($service, $request->getFields(), $resolver);

        return response()->json([
            'data' => $effectiveFields,
            'message' => 'Service booking fields updated successfully.',
        ]);
    }

    private function ensureTenantOwnership(Request $request, int $resourceTenantId): void
    {
        $tenantId = $request->user()?->tenant_id;

        if (! is_int($tenantId) || $tenantId !== $resourceTenantId) {
            abort(404);
        }
    }
}
