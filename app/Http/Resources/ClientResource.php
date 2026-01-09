<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
final class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \Carbon\Carbon|null $lastVisitAt */
        $lastVisitAt = $this->last_visit_at;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'notes' => $this->notes,
            'status' => $this->status,
            'total_visits' => $this->total_visits,
            'total_spent' => $this->total_spent,
            'last_visit_at' => $lastVisitAt?->toIso8601String(),
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
        ];
    }
}
