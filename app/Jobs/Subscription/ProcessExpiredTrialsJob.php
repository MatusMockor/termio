<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\TrialEndedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProcessExpiredTrialsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(
        SubscriptionRepository $subscriptions,
        PlanRepository $plans,
    ): void {
        $freePlan = $plans->getFreePlan();

        if (! $freePlan) {
            Log::error('Cannot process expired trials: FREE plan not found');

            return;
        }

        Subscription::with(['tenant.owner', 'plan'])
            ->where('stripe_status', SubscriptionStatus::Trialing)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->chunk(100, function ($expiredTrials) use ($subscriptions, $freePlan): void {
                /** @var Subscription $subscription */
                foreach ($expiredTrials as $subscription) {
                    $this->processExpiredTrial($subscription, $subscriptions, $freePlan);
                }
            });
    }

    private function processExpiredTrial(
        Subscription $subscription,
        SubscriptionRepository $subscriptions,
        Plan $freePlan,
    ): void {
        /** @var \App\Models\Tenant|null $tenant */
        $tenant = $subscription->tenant;

        if ($tenant === null) {
            Log::error('Cannot process expired trial: tenant not found', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        if ($tenant->hasDefaultPaymentMethod()) {
            $this->convertToActiveSubscription($subscription, $subscriptions, $tenant);

            return;
        }

        $this->downgradeToFreePlan($subscription, $subscriptions, $freePlan, $tenant);
    }

    private function convertToActiveSubscription(
        Subscription $subscription,
        SubscriptionRepository $subscriptions,
        \App\Models\Tenant $tenant,
    ): void {
        $subscriptions->update($subscription, [
            'stripe_status' => SubscriptionStatus::Active->value,
            'trial_ends_at' => null,
        ]);

        $owner = $tenant->owner;

        if ($owner) {
            $owner->notify(new TrialEndedNotification($tenant, true));
        }

        Log::info('Trial converted to active subscription', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    private function downgradeToFreePlan(
        Subscription $subscription,
        SubscriptionRepository $subscriptions,
        Plan $freePlan,
        \App\Models\Tenant $tenant,
    ): void {
        DB::transaction(static function () use ($subscription, $freePlan, $subscriptions, $tenant): void {
            if (! str_starts_with($subscription->stripe_id, 'free_')) {
                $stripeSub = $tenant->subscription(SubscriptionType::Default->value);

                if ($stripeSub) {
                    $stripeSub->cancelNow();
                }
            }

            $subscriptions->update($subscription, [
                'plan_id' => $freePlan->id,
                'stripe_id' => 'free_'.$tenant->id,
                'stripe_status' => SubscriptionStatus::Active->value,
                'stripe_price' => null,
                'trial_ends_at' => null,
            ]);
        });

        $owner = $tenant->owner;

        if ($owner) {
            $owner->notify(new TrialEndedNotification($tenant, false));

            Log::info('Expired trial processed - downgraded to FREE plan and notification sent', [
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
            ]);
        }

        Log::info('Expired trial processed - downgraded to FREE plan', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
        ]);
    }
}
