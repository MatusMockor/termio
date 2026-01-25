<?php

declare(strict_types=1);

namespace App\Http\Resources\Onboarding;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OnboardingStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'completed' => $this->resource['completed'],
            'business_type' => $this->resource['business_type'],
            'current_step' => $this->resource['current_step'],
            'data' => $this->resource['data'],
            'completed_at' => $this->resource['completed_at'],
        ];
    }
}
