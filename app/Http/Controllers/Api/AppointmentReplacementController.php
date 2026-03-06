<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Waitlist\AppointmentReplaceFromWaitlistAction;
use App\Http\Requests\Waitlist\ReplaceFromWaitlistRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\Appointment;
use App\Models\WaitlistEntry;
use App\Services\Waitlist\WaitlistMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AppointmentReplacementController extends ApiController
{
    public function replacementCandidates(
        Request $request,
        Appointment $appointment,
        WaitlistMatchingService $matchingService,
    ): AnonymousResourceCollection {
        $this->ensureTenantOwnership($request, $appointment->tenant_id);

        $candidates = $matchingService->getReplacementCandidates($appointment);

        return WaitlistEntryResource::collection($candidates);
    }

    public function replaceFromWaitlist(
        ReplaceFromWaitlistRequest $request,
        Appointment $appointment,
        AppointmentReplaceFromWaitlistAction $action,
    ): JsonResponse {
        $this->ensureTenantOwnership($request, $appointment->tenant_id);

        $entry = WaitlistEntry::forTenant($appointment->tenant_id)->findOrFail($request->getWaitlistEntryId());

        $this->ensureTenantOwnership($request, $entry->tenant_id);

        $replacementAppointment = $action->handle($appointment, $entry, $request->getNotes());

        return AppointmentResource::make($replacementAppointment)
            ->response()
            ->setStatusCode(201);
    }

    private function ensureTenantOwnership(Request $request, int $resourceTenantId): void
    {
        $tenantId = $request->user()?->tenant_id;

        if (! is_int($tenantId) || $tenantId !== $resourceTenantId) {
            abort(404);
        }
    }
}
