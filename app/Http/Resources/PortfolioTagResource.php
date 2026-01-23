<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PortfolioTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PortfolioTag
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class PortfolioTagResource extends JsonResource
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
            'color' => $this->color,
        ];
    }
}
