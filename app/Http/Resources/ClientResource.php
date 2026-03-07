<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
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
            'tags' => ClientTagResource::collection($this->whenLoaded('tags')),
            'booking_controls' => [
                'is_blacklisted' => $this->is_blacklisted,
                'is_whitelisted' => $this->is_whitelisted,
                'booking_control_note' => $this->booking_control_note,
            ],
            'anti_no_show' => [
                'no_show_count' => $this->no_show_count,
                'late_cancellation_count' => $this->late_cancellation_count,
                'last_no_show_at' => $this->last_no_show_at?->toIso8601String(),
                'last_late_cancellation_at' => $this->last_late_cancellation_at?->toIso8601String(),
                'risk_level' => $this->risk_level->value,
            ],
            'can_book_online' => $this->canBookOnline(),
            'total_visits' => $this->total_visits,
            'total_spent' => $this->total_spent,
            'last_visit_at' => $lastVisitAt?->toIso8601String(),
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
        ];
    }
}
