<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Service\UpdateServiceBookingFieldsRequest;
use App\Models\Service;
use App\Models\ServiceBookingFieldOverride;
use App\Services\Booking\Fields\BookingFieldResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceBookingFieldOverrideController extends ApiController
{
    public function update(
        UpdateServiceBookingFieldsRequest $request,
        Service $service,
        BookingFieldResolverService $resolver,
    ): JsonResponse {
        $this->ensureTenantOwnership($request, $service->tenant_id);

        ServiceBookingFieldOverride::where('service_id', $service->id)->delete();

        foreach ($request->getFields() as $field) {
            ServiceBookingFieldOverride::create([
                'service_id' => $service->id,
                'booking_field_id' => $field['booking_field_id'],
                'is_enabled' => $field['is_enabled'],
                'is_required' => $field['is_required'],
            ]);
        }

        $effectiveFields = $resolver->resolveForService($service->tenant, $service);

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
