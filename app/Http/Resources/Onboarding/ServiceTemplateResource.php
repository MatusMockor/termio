<?php

declare(strict_types=1);

namespace App\Http\Resources\Onboarding;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource['name'],
            'duration_minutes' => $this->resource['duration_minutes'],
            'price' => $this->resource['price'],
        ];
    }
}
