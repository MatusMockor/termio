<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use App\Enums\PlanSlug;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class DashboardPlanOptionResource extends JsonResource
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
            'pricing' => [
                'monthly' => [
                    'amount' => (float) $this->monthly_price,
                    'currency' => 'EUR',
                ],
                'yearly' => [
                    'amount' => (float) $this->yearly_price,
                    'currency' => 'EUR',
                ],
            ],
            'is_popular' => $this->slug === PlanSlug::Smart->value,
        ];
    }
}
