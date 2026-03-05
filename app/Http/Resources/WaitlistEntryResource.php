<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WaitlistEntry
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class WaitlistEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'preferred_staff_id' => $this->preferred_staff_id,
            'preferred_date' => $this->preferred_date?->toDateString(),
            'time_from' => $this->time_from,
            'time_to' => $this->time_to,
            'client_name' => $this->client_name,
            'client_phone' => $this->client_phone,
            'client_email' => $this->client_email,
            'notes' => $this->notes,
            'status' => $this->resolveEnumValue($this->status),
            'source' => $this->resolveEnumValue($this->source),
            'converted_appointment_id' => $this->converted_appointment_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    private function resolveEnumValue(mixed $value): string
    {
        if (is_object($value) && property_exists($value, 'value')) {
            /** @var string $enumValue */
            $enumValue = $value->value;

            return $enumValue;
        }

        return is_string($value) ? $value : '';
    }
}
