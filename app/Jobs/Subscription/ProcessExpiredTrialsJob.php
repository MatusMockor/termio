<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\TrialEndedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes expired trial subscriptions.
 *
 * For each expired trial:
 * - If tenant has a payment method: converts to active subscription
 * - If tenant has no payment method: downgrades to free plan
 */
final class ProcessExpiredTrialsJob extends AbstractSubscriptionProcessingJob
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
    ) {}

    protected function getJobName(): string
    {
        return 'ProcessExpiredTrials';
    }

    /**
     * @return Builder<Subscription>
     */
    protected function buildQuery(): Builder
    {
        return Subscription::query()
            ->with(['tenant.owner', 'plan'])
            ->where('stripe_status', SubscriptionStatus::Trialing)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now());
    }

    protected function processItem(Model $item): void
    {
        if (! ($item instanceof Subscription)) {
            return;
        }

        try {
            $this->processExpiredTrial($item);
        } catch (\Throwable $exception) {
            $this->handleError($exception, $item);
        }
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength) Method is actually ~29 lines, PHPMD bug with directory scanning
     */
    private function processExpiredTrial(Subscription $subscription): void
    {
        $freePlan = $this->plans->getFreePlan();

        if (! $freePlan) {
            Log::error('Cannot process expired trial: FREE plan not found');

            return;
        }

        /** @var Tenant|null $tenant */
        $tenant = $subscription->tenant;

        if (! $tenant) {
            Log::error('Cannot process expired trial: tenant not found', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        if ($tenant->hasDefaultPaymentMethod()) {
            $this->convertToActiveSubscription($subscription, $tenant);

            return;
        }

        $this->downgradeToFreePlan($subscription, $freePlan, $tenant);
    }

    private function convertToActiveSubscription(Subscription $subscription, Tenant $tenant): void
    {
        $this->subscriptions->update($subscription, [
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
        Plan $freePlan,
        Tenant $tenant,
    ): void {
        DB::transaction(function () use ($subscription, $freePlan, $tenant): void {
            if (! str_starts_with($subscription->stripe_id, 'free_')) {
                $stripeSub = $tenant->subscription(SubscriptionType::Default->value);

                if ($stripeSub) {
                    $stripeSub->cancelNow();
                }
            }

            $this->subscriptions->update($subscription, [
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
