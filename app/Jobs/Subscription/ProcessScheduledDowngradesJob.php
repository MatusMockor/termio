<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Models\Subscription;
use App\Notifications\SubscriptionDowngradedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProcessScheduledDowngradesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(SubscriptionRepository $subscriptions): void
    {
        $scheduledDowngrades = $subscriptions->getScheduledDowngrades();

        /** @var Subscription $subscription */
        foreach ($scheduledDowngrades as $subscription) {
            $this->processDowngrade($subscription, $subscriptions);
        }
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function processDowngrade(
        Subscription $subscription,
        SubscriptionRepository $subscriptions,
    ): void {
        $subscription->load(['tenant.owner', 'scheduledPlan']);

        /** @var \App\Models\Tenant|null $tenant */
        $tenant = $subscription->tenant;
        /** @var \App\Models\Plan|null $scheduledPlan */
        $scheduledPlan = $subscription->scheduledPlan;

        if ($tenant === null || $scheduledPlan === null) {
            Log::error('Cannot process scheduled downgrade: missing tenant or plan', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'scheduled_plan_id' => $subscription->scheduled_plan_id,
            ]);

            return;
        }

        $this->executeDowngrade($subscription, $scheduledPlan, $subscriptions);
        $this->sendDowngradeNotification($subscription, $tenant, $scheduledPlan);
    }

    private function executeDowngrade(
        Subscription $subscription,
        \App\Models\Plan $scheduledPlan,
        SubscriptionRepository $subscriptions,
    ): void {
        DB::transaction(function () use ($subscription, $scheduledPlan, $subscriptions): void {
            $priceId = $this->getNewPriceId($subscription, $scheduledPlan);
            $this->swapStripeSubscription($subscription, $priceId);
            $this->updateSubscriptionPlan($subscription, $scheduledPlan, $priceId, $subscriptions);
        });
    }

    private function getNewPriceId(Subscription $subscription, \App\Models\Plan $scheduledPlan): ?string
    {
        return $subscription->billing_cycle === 'yearly'
            ? $scheduledPlan->stripe_yearly_price_id
            : $scheduledPlan->stripe_monthly_price_id;
    }

    private function swapStripeSubscription(Subscription $subscription, ?string $priceId): void
    {
        if (str_starts_with($subscription->stripe_id, 'free_')) {
            return;
        }

        $stripeSub = $subscription->tenant->subscription('default');

        if ($stripeSub && $priceId) {
            $stripeSub->swap($priceId);
        }
    }

    private function updateSubscriptionPlan(
        Subscription $subscription,
        \App\Models\Plan $scheduledPlan,
        ?string $priceId,
        SubscriptionRepository $subscriptions,
    ): void {
        $subscriptions->update($subscription, [
            'plan_id' => $scheduledPlan->id,
            'stripe_price' => $priceId,
            'scheduled_plan_id' => null,
            'scheduled_change_at' => null,
        ]);
    }

    private function sendDowngradeNotification(
        Subscription $subscription,
        \App\Models\Tenant $tenant,
        \App\Models\Plan $scheduledPlan,
    ): void {
        $owner = $tenant->owner;

        if ($owner) {
            $owner->notify(new SubscriptionDowngradedNotification($tenant, $scheduledPlan));
            $this->logDowngradeWithNotification($tenant, $owner, $scheduledPlan);

            return;
        }

        $this->logDowngrade($subscription, $tenant, $scheduledPlan);
    }

    private function logDowngradeWithNotification(
        \App\Models\Tenant $tenant,
        \App\Models\User $owner,
        \App\Models\Plan $scheduledPlan,
    ): void {
        Log::info('Scheduled downgrade processed and notification sent', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'new_plan_id' => $scheduledPlan->id,
        ]);
    }

    private function logDowngrade(
        Subscription $subscription,
        \App\Models\Tenant $tenant,
        \App\Models\Plan $scheduledPlan,
    ): void {
        Log::info('Scheduled downgrade processed', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'new_plan_id' => $scheduledPlan->id,
        ]);
    }
}
