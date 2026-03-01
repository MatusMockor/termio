<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PlanSlug;
use App\Models\Plan;
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
            'plan' => $this->buildPlanPayload($this->whenLoaded('plan')),
            'billing_cycle' => $this->billing_cycle,
            'status' => $this->stripe_status->value,
            'quantity' => $this->quantity,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'scheduled_plan_id' => $this->scheduled_plan_id,
            'scheduled_plan' => $this->buildPlanPayload($this->whenLoaded('scheduledPlan')),
            'scheduled_change_at' => $this->scheduled_change_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPlanPayload(mixed $plan): ?array
    {
        if (! $plan instanceof Plan) {
            return null;
        }

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'monthly_price' => $plan->monthly_price,
            'yearly_price' => $plan->yearly_price,
            'pricing' => [
                'monthly' => (float) $plan->monthly_price,
                'yearly' => (float) $plan->yearly_price,
                'yearly_monthly_equivalent' => $this->getYearlyMonthlyEquivalent($plan),
                'yearly_discount_percent' => $this->getYearlyDiscountPercentage($plan),
            ],
            'features' => $plan->features,
            'limits' => $plan->limits,
            'is_popular' => $plan->slug === PlanSlug::Smart->value,
        ];
    }

    private function getYearlyMonthlyEquivalent(Plan $plan): float
    {
        return round((float) $plan->yearly_price / 12, 2);
    }

    private function getYearlyDiscountPercentage(Plan $plan): float
    {
        $monthlyPrice = (float) $plan->monthly_price;

        if ($monthlyPrice <= 0) {
            return 0.0;
        }

        $annualMonthlyTotal = $monthlyPrice * 12;
        $savings = $annualMonthlyTotal - (float) $plan->yearly_price;

        return round(($savings / $annualMonthlyTotal) * 100, 0);
    }
}
