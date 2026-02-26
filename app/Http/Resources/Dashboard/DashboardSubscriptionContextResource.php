<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class DashboardSubscriptionContextResource extends JsonResource
{
    /**
     * @param  array{
     *     current_plan: Plan,
     *     subscription: \App\Models\Subscription|null,
     *     is_on_trial: bool,
     *     trial_days_remaining: int,
     *     pending_change: array{type: string, plan: Plan|null, date: \Carbon\Carbon|null}|null,
     *     is_free_subscription: bool,
     *     upgrade_options: array<int, Plan>,
     *     recommended_plan_id: int|null,
     *     actions: array{
     *         next_action: string,
     *         create_endpoint: string,
     *         upgrade_endpoint: string,
     *         default_billing_cycle: string,
     *         requires_default_payment_method: bool,
     *         has_default_payment_method: bool
     *     }
     * } $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        $currentPlan = DashboardPlanOptionResource::make($this->resource['current_plan'])->resolve();
        $subscription = $this->resource['subscription'];
        $pendingChange = $this->resource['pending_change'];

        return [
            'current_plan' => $currentPlan,
            'subscription' => [
                'id' => $subscription?->id,
                'status' => $subscription?->stripe_status?->value,
                'billing_cycle' => $subscription?->billing_cycle,
                'is_on_trial' => $this->resource['is_on_trial'],
                'trial_days_remaining' => $this->resource['trial_days_remaining'],
                'pending_change' => $this->formatPendingChange($pendingChange),
                'is_free_subscription' => $this->resource['is_free_subscription'],
            ],
            'upgrade_options' => DashboardPlanOptionResource::collection($this->resource['upgrade_options'])->resolve(),
            'recommended_plan_id' => $this->resource['recommended_plan_id'],
            'actions' => $this->resource['actions'],
        ];
    }

    /**
     * @param  array{type: string, plan: Plan|null, date: \Carbon\Carbon|null}|null  $pendingChange
     * @return array{type: string, plan: array{id: int|null, name: string|null, slug: string|null}|null, date: string|null}|null
     */
    private function formatPendingChange(?array $pendingChange): ?array
    {
        if ($pendingChange === null) {
            return null;
        }

        $plan = $pendingChange['plan'];

        return [
            'type' => $pendingChange['type'],
            'plan' => $plan !== null
                ? [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                ]
                : null,
            'date' => $pendingChange['date']?->toIso8601String(),
        ];
    }
}
