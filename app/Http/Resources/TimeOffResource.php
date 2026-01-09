<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TimeOff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimeOff
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class TimeOffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        /** @var \Carbon\Carbon|null $date */
        $date = $this->date;

        return [
            'id' => $this->id,
            'staff_id' => $this->staff_id,
            'date' => $date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'is_all_day' => $this->resource->isAllDay(),
            'reason' => $this->reason,
            'staff' => new StaffProfileResource($this->whenLoaded('staff')),
        ];
    }
}
