<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class PlanResource extends JsonResource
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
            'description' => $this->description,
            'monthly_price' => $this->monthly_price,
            'yearly_price' => $this->yearly_price,
            'stripe_monthly_price_id' => $this->stripe_monthly_price_id,
            'stripe_yearly_price_id' => $this->stripe_yearly_price_id,
            'features' => $this->features,
            'limits' => $this->limits,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            'subscriber_count' => $this->whenLoaded('subscriptions', function (): int {
                return $this->subscriptions
                    ->where('stripe_status', 'active')
                    ->whereNull('ends_at')
                    ->count();
            }, $this->subscriber_count ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
