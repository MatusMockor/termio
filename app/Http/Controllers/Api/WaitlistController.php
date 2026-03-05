<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Waitlist\WaitlistConvertToAppointmentAction;
use App\Enums\WaitlistEntrySource;
use App\Http\Requests\Waitlist\ConvertWaitlistEntryRequest;
use App\Http\Requests\Waitlist\StoreWaitlistEntryRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WaitlistController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        $entries = WaitlistEntry::with(['service', 'preferredStaff'])
            ->when(request()->filled('status'), static function ($query): void {
                $query->where('status', request()->string('status')->toString());
            })
            ->when(request()->filled('service_id'), static function ($query): void {
                $query->where('service_id', (int) request()->integer('service_id'));
            })
            ->when(request()->filled('preferred_date'), static function ($query): void {
                $query->where('preferred_date', request()->string('preferred_date')->toString());
            })
            ->ordered()
            ->paginate((int) request()->integer('per_page', 15));

        return WaitlistEntryResource::collection($entries);
    }

    public function store(StoreWaitlistEntryRequest $request): WaitlistEntryResource
    {
        $tenant = $this->resolveTenantOrFail($request);
        $payload = $request->getWaitlistData();

        $entry = WaitlistEntry::create([
            ...$payload,
            'tenant_id' => $tenant->id,
            'source' => WaitlistEntrySource::Owner->value,
        ]);

        return new WaitlistEntryResource($entry->refresh());
    }

    public function convert(
        ConvertWaitlistEntryRequest $request,
        WaitlistEntry $entry,
        WaitlistConvertToAppointmentAction $action,
    ): AppointmentResource {
        $this->ensureTenantOwnership($request, $entry->tenant_id);

        $appointment = $action->handle(
            $entry,
            $request->getStartsAt(),
            $request->getStaffId(),
            $request->getNotes(),
        );

        return new AppointmentResource($appointment);
    }

    private function resolveTenantOrFail(StoreWaitlistEntryRequest $request): Tenant
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
