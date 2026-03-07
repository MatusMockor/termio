<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ClientTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClientTag
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class ClientTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
