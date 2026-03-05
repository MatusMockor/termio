<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCategory
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class ServiceCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'priority' => $this->priority,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
