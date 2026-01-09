<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffProfile
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class StaffProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'photo_url' => $this->photo_url,
            'specializations' => $this->specializations,
            'is_bookable' => $this->is_bookable,
            'sort_order' => $this->sort_order,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
        ];
    }
}
