<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Notifications\SubscriptionEndedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class HandleSubscriptionCanceledJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $subscriptionId,
    ) {}

    public function handle(
        SubscriptionRepository $subscriptions,
        PlanRepository $plans
    ): void {
        $subscription = $subscriptions->findById($this->subscriptionId);

        if ($subscription === null) {
            Log::warning('HandleSubscriptionCanceledJob: subscription not found', [
                'subscription_id' => $this->subscriptionId,
            ]);

            return;
        }

        $tenant = $subscription->tenant;
        $freePlan = $plans->getFreePlan();

        if ($freePlan === null) {
            Log::error('HandleSubscriptionCanceledJob: free plan not found', [
                'subscription_id' => $this->subscriptionId,
            ]);

            return;
        }

        // Update subscription to free plan
        $subscriptions->update($subscription, [
            'plan_id' => $freePlan->id,
            'stripe_status' => 'canceled',
            'ends_at' => now(),
            'scheduled_plan_id' => null,
            'scheduled_change_at' => null,
        ]);

        // Notify owner
        $owner = $tenant->owner;

        if ($owner !== null) {
            $owner->notify(new SubscriptionEndedNotification($tenant));
        }

        Log::info('HandleSubscriptionCanceledJob: subscription ended, downgraded to FREE', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
        ]);
    }
}
