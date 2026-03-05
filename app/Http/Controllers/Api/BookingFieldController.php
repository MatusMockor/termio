<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\BookingField\StoreBookingFieldRequest;
use App\Http\Requests\BookingField\UpdateBookingFieldRequest;
use App\Http\Resources\BookingFieldResource;
use App\Models\BookingField;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class BookingFieldController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        $fields = BookingField::ordered()->get();

        return BookingFieldResource::collection($fields);
    }

    public function store(StoreBookingFieldRequest $request): BookingFieldResource
    {
        $tenant = $this->resolveTenantOrFail($request);

        $field = BookingField::create([
            'tenant_id' => $tenant->id,
            'key' => $request->getKey(),
            'label' => $request->getLabel(),
            'type' => $request->getType()->value,
            'options' => $request->getOptions(),
            'is_required' => $request->isRequired(),
            'is_active' => $request->isActive(),
            'sort_order' => $request->getSortOrder(),
        ]);

        return new BookingFieldResource($field);
    }

    public function update(UpdateBookingFieldRequest $request, BookingField $field): BookingFieldResource
    {
        $this->ensureTenantOwnership($request, $field->tenant_id);

        $field->update($request->getUpdateData());

        return new BookingFieldResource($field->refresh());
    }

    public function destroy(Request $request, BookingField $field): JsonResponse
    {
        $this->ensureTenantOwnership($request, $field->tenant_id);

        $field->delete();

        return response()->json(null, 204);
    }

    private function resolveTenantOrFail(StoreBookingFieldRequest $request): Tenant
    {
        $tenantId = $request->user()?->tenant_id;

        return Tenant::where('id', $tenantId)->firstOrFail();
    }

    private function ensureTenantOwnership(Request $request, int $resourceTenantId): void
    {
        $tenantId = $request->user()?->tenant_id;

        if (! is_int($tenantId) || $tenantId !== $resourceTenantId) {
            abort(404);
        }
    }
}
