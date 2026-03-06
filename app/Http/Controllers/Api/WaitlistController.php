<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Waitlist\WaitlistConvertToAppointmentAction;
use App\Enums\WaitlistEntrySource;
use App\Http\Requests\Waitlist\ConvertWaitlistEntryRequest;
use App\Http\Requests\Waitlist\ListWaitlistEntriesRequest;
use App\Http\Requests\Waitlist\StoreWaitlistEntryRequest;
use App\Http\Requests\Waitlist\UpdateWaitlistEntryRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use App\Services\Waitlist\WaitlistEntryValidationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WaitlistController extends ApiController
{
    public function index(ListWaitlistEntriesRequest $request): AnonymousResourceCollection
    {
        $tenant = $this->resolveTenantOrFail($request);

        $entries = WaitlistEntry::forTenant($tenant->id)
            ->with(['service', 'preferredStaff', 'convertedAppointment'])
            ->when($request->getStatus() !== null, static function (Builder $query) use ($request): void {
                $query->where('status', $request->getStatus());
            })
            ->when($request->getSource() !== null, static function (Builder $query) use ($request): void {
                $query->where('source', $request->getSource());
            })
            ->when($request->getServiceId() !== null, static function (Builder $query) use ($request): void {
                $query->where('service_id', $request->getServiceId());
            })
            ->when($request->getPreferredStaffId() !== null, static function (Builder $query) use ($request): void {
                $query->where('preferred_staff_id', $request->getPreferredStaffId());
            })
            ->when($request->getPreferredDate() !== null, static function (Builder $query) use ($request): void {
                $query->whereDate('preferred_date', $request->getPreferredDate());
            })
            ->when($request->getSearch() !== null, static function (Builder $query) use ($request): void {
                $search = '%'.$request->getSearch().'%';

                $query->where(static function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('client_name', 'like', $search)
                        ->orWhere('client_phone', 'like', $search)
                        ->orWhere('client_email', 'like', $search);
                });
            })
            ->ordered()
            ->paginate($request->getPerPage());

        return WaitlistEntryResource::collection($entries);
    }

    public function store(
        StoreWaitlistEntryRequest $request,
        WaitlistEntryValidationService $validationService,
    ): JsonResponse {
        $tenant = $this->resolveTenantOrFail($request);
        $payload = $request->getWaitlistData();
        $preferredStaffId = $payload['preferred_staff_id'] ?? null;

        $validationService->ensureStaffSupportsService(
            $tenant,
            (int) $payload['service_id'],
            is_int($preferredStaffId) ? $preferredStaffId : null,
        );

        $entry = WaitlistEntry::create([
            ...$payload,
            'tenant_id' => $tenant->id,
            'source' => WaitlistEntrySource::Owner->value,
        ]);

        return WaitlistEntryResource::make(
            $entry->refresh()->load(['service', 'preferredStaff', 'convertedAppointment']),
        )->response()->setStatusCode(201);
    }

    public function update(
        UpdateWaitlistEntryRequest $request,
        WaitlistEntry $entry,
        WaitlistEntryValidationService $validationService,
    ): WaitlistEntryResource {
        $this->ensureTenantOwnership($request, $entry->tenant_id);

        $tenant = $this->resolveTenantOrFail($request);
        $payload = $request->safe()->only([
            'preferred_staff_id',
            'preferred_date',
            'time_from',
            'time_to',
            'client_name',
            'client_phone',
            'client_email',
            'notes',
            'status',
        ]);

        if (array_key_exists('time_from', $payload)) {
            $payload['time_from'] = $request->getTimeFrom();
        }

        if (array_key_exists('time_to', $payload)) {
            $payload['time_to'] = $request->getTimeTo();
        }

        if (array_key_exists('status', $payload)) {
            $payload['status'] = $request->getStatus()?->value;
        }

        $preferredStaffId = $request->hasPreferredStaffId()
            ? $request->getPreferredStaffId()
            : $entry->preferred_staff_id;

        $validationService->ensureStaffSupportsService(
            $tenant,
            $entry->service_id,
            $preferredStaffId,
        );

        $entry->update($payload);

        return new WaitlistEntryResource($entry->refresh()->load(['service', 'preferredStaff', 'convertedAppointment']));
    }

    public function convert(
        ConvertWaitlistEntryRequest $request,
        WaitlistEntry $entry,
        WaitlistConvertToAppointmentAction $action,
    ): JsonResponse {
        $this->ensureTenantOwnership($request, $entry->tenant_id);

        $appointment = $action->handle(
            $entry,
            $request->getStartsAt(),
            $request->getStaffId(),
            $request->getNotes(),
        );

        return AppointmentResource::make($appointment)
            ->response()
            ->setStatusCode(201);
    }

    private function resolveTenantOrFail(Request $request): Tenant
    {
        $tenantId = $request->user()?->tenant_id;

        if (! is_int($tenantId)) {
            abort(401);
        }

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
