<?php

declare(strict_types=1);

namespace App\Http\Resources\Onboarding;

use App\DTOs\Onboarding\OnboardingStatusDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OnboardingStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof OnboardingStatusDTO) {
            return $this->resource->toArray();
        }

        return [
            'completed' => $this->resource['completed'],
            'business_type' => $this->resource['business_type'],
            'current_step' => $this->resource['current_step'],
            'data' => $this->resource['data'],
            'completed_at' => $this->resource['completed_at'],
        ];
    }
}
