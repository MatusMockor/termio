<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PortfolioImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PortfolioImage
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class PortfolioImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => $this->staff_id,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'sort_order' => $this->sort_order,
            'is_public' => $this->is_public,
            'tags' => PortfolioTagResource::collection($this->whenLoaded('tags')),
            'staff' => new StaffProfileResource($this->whenLoaded('staff')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
