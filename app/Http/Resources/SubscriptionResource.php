<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'type' => $this->type,
            'stripe_id' => $this->stripe_id,
            'stripe_status' => $this->stripe_status->value,
            'stripe_price' => $this->stripe_price,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'billing_cycle' => $this->billing_cycle,
            'status' => $this->stripe_status->value,
            'quantity' => $this->quantity,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'scheduled_plan_id' => $this->scheduled_plan_id,
            'scheduled_plan' => new PlanResource($this->whenLoaded('scheduledPlan')),
            'scheduled_change_at' => $this->scheduled_change_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
