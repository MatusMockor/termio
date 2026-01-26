<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionDowngradedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes scheduled subscription downgrades.
 *
 * For each subscription with a scheduled downgrade that is due:
 * - Executes the plan change (swaps Stripe subscription if applicable)
 * - Updates the subscription record
 * - Sends notification to the tenant owner
 */
final class ProcessScheduledDowngradesJob extends AbstractSubscriptionProcessingJob
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    protected function getJobName(): string
    {
        return 'ProcessScheduledDowngrades';
    }

    /**
     * @return Builder<Subscription>
     */
    protected function buildQuery(): Builder
    {
        return Subscription::query()
            ->with(['tenant.owner', 'scheduledPlan'])
            ->whereNotNull('scheduled_plan_id')
            ->whereNotNull('scheduled_change_at')
            ->where('scheduled_change_at', '<=', now());
    }

    protected function processItem(Model $item): void
    {
        if (! ($item instanceof Subscription)) {
            return;
        }

        try {
            $this->processDowngrade($item);
        } catch (\Throwable $exception) {
            $this->handleError($exception, $item);
        }
    }

    private function processDowngrade(Subscription $subscription): void
    {
        /** @var Tenant|null $tenant */
        $tenant = $subscription->tenant;
        /** @var Plan|null $scheduledPlan */
        $scheduledPlan = $subscription->scheduledPlan;

        if (! $tenant || ! $scheduledPlan) {
            Log::error('Cannot process scheduled downgrade: missing tenant or plan', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'scheduled_plan_id' => $subscription->scheduled_plan_id,
            ]);

            return;
        }

        $this->executeDowngrade($subscription, $scheduledPlan);
        $this->sendDowngradeNotification($subscription, $tenant, $scheduledPlan);
    }

    private function executeDowngrade(Subscription $subscription, Plan $scheduledPlan): void
    {
        DB::transaction(function () use ($subscription, $scheduledPlan): void {
            $priceId = $subscription->billing_cycle === 'yearly'
                ? $scheduledPlan->stripe_yearly_price_id
                : $scheduledPlan->stripe_monthly_price_id;

            if (! str_starts_with($subscription->stripe_id, 'free_')) {
                $stripeSub = $subscription->tenant->subscription('default');

                if ($stripeSub && $priceId) {
                    $stripeSub->swap($priceId);
                }
            }

            $this->subscriptions->update($subscription, [
                'plan_id' => $scheduledPlan->id,
                'stripe_price' => $priceId,
                'scheduled_plan_id' => null,
                'scheduled_change_at' => null,
            ]);
        });
    }

    private function sendDowngradeNotification(
        Subscription $subscription,
        Tenant $tenant,
        Plan $scheduledPlan,
    ): void {
        $owner = $tenant->owner;

        if ($owner) {
            $owner->notify(new SubscriptionDowngradedNotification($tenant, $scheduledPlan));

            Log::info('Scheduled downgrade processed and notification sent', [
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'new_plan_id' => $scheduledPlan->id,
            ]);
        }

        Log::info('Scheduled downgrade processed', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'new_plan_id' => $scheduledPlan->id,
        ]);
    }
}
