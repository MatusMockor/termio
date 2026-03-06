<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Service\UpdateServiceBookingFieldOverridesAction;
use App\Http\Requests\Service\UpdateServiceBookingFieldsRequest;
use App\Http\Resources\ServiceBookingFieldConfigResource;
use App\Models\Service;
use App\Services\Booking\Fields\BookingFieldResolverService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ServiceBookingFieldOverrideController extends ApiController
{
    public function index(
        Request $request,
        Service $service,
        BookingFieldResolverService $resolver,
    ): AnonymousResourceCollection {
        $this->ensureTenantOwnership($request, $service->tenant_id);

        return ServiceBookingFieldConfigResource::collection(
            $resolver->getServiceConfiguration($service->tenant, $service),
        );
    }

    public function update(
        UpdateServiceBookingFieldsRequest $request,
        Service $service,
        BookingFieldResolverService $resolver,
        UpdateServiceBookingFieldOverridesAction $action,
    ): AnonymousResourceCollection {
        $this->ensureTenantOwnership($request, $service->tenant_id);

        return ServiceBookingFieldConfigResource::collection(
            $action->execute($service, $request->getFields(), $resolver),
        );
    }

    private function ensureTenantOwnership(Request $request, int $resourceTenantId): void
    {
        $tenantId = $request->user()?->tenant_id;

        if (! is_int($tenantId) || $tenantId !== $resourceTenantId) {
            abort(404);
        }
    }
}
