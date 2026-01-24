<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionDowngradedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */
final class HandlePaymentFailedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly int $tenantId,
        private readonly int $attemptCount,
    ) {}

    public function handle(SubscriptionRepository $subscriptions, PlanRepository $plans): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            Log::warning('HandlePaymentFailedJob: tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        $owner = $tenant->owner;
        if ($owner === null) {
            Log::warning('HandlePaymentFailedJob: owner not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        $this->notifyPaymentFailed($owner, $tenant);

        if ($this->attemptCount >= self::MAX_ATTEMPTS) {
            $this->downgradeToFree($tenant, $owner, $subscriptions, $plans);
        }
    }

    private function notifyPaymentFailed(User $owner, Tenant $tenant): void
    {
        $owner->notify(new PaymentFailedNotification($tenant, $this->attemptCount));
        Log::info('HandlePaymentFailedJob: notification sent', ['tenant_id' => $tenant->id]);
    }

    private function downgradeToFree(
        Tenant $tenant,
        User $owner,
        SubscriptionRepository $subscriptions,
        PlanRepository $plans
    ): void {
        $subscription = $subscriptions->findActiveByTenant($tenant);
        if ($subscription === null) {
            return;
        }

        $freePlan = $plans->getFreePlan();
        if ($freePlan === null) {
            Log::error('HandlePaymentFailedJob: free plan not found');

            return;
        }

        $this->applyDowngrade($subscription, $freePlan, $owner, $tenant, $subscriptions);
    }

    private function applyDowngrade(
        Subscription $subscription,
        Plan $freePlan,
        User $owner,
        Tenant $tenant,
        SubscriptionRepository $subscriptions
    ): void {
        $subscriptions->update($subscription, ['plan_id' => $freePlan->id, 'stripe_status' => 'canceled']);
        $owner->notify(new SubscriptionDowngradedNotification($tenant, $freePlan));
        Log::warning('HandlePaymentFailedJob: downgraded', ['subscription_id' => $subscription->id]);
    }
}
