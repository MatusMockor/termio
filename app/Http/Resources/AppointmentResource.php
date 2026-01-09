<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        /** @var \Carbon\Carbon|null $startsAt */
        $startsAt = $this->starts_at;
        /** @var \Carbon\Carbon|null $endsAt */
        $endsAt = $this->ends_at;

        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'service_id' => $this->service_id,
            'staff_id' => $this->staff_id,
            'starts_at' => $startsAt?->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
            'status' => $this->status,
            'notes' => $this->notes,
            'source' => $this->source,
            'client' => new ClientResource($this->whenLoaded('client')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'staff' => new StaffProfileResource($this->whenLoaded('staff')),
        ];
    }
}
