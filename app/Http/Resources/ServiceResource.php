<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        $categoryRelation = $this->resource->relationLoaded('categoryRelation')
            ? $this->categoryRelation
            : null;

        $category = null;

        if ($categoryRelation !== null) {
            $category = [
                'id' => $categoryRelation->id,
                'name' => $categoryRelation->name,
                'parent_id' => $categoryRelation->parent_id,
            ];
        } elseif (is_string($this->category) && $this->category !== '') {
            $category = [
                'id' => null,
                'name' => $this->category,
                'parent_id' => null,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
            'price' => $this->price,
            'category_id' => $this->category_id,
            'category' => $category,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'is_bookable_online' => $this->is_bookable_online,
            'sort_order' => $this->sort_order,
        ];
    }
}
