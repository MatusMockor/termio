<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionResumedNotification;
use Illuminate\Support\Facades\DB;

final class SubscriptionResumeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    public function handle(int $subscriptionId): Subscription
    {
        $subscription = $this->subscriptions->findById($subscriptionId);

        if (! $subscription) {
            throw SubscriptionException::subscriptionNotFound($subscriptionId);
        }

        if (! $subscription->ends_at) {
            throw SubscriptionException::notCanceled();
        }

        if ($subscription->ends_at->isPast()) {
            throw SubscriptionException::cancellationAlreadyEffective();
        }

        $tenant = $subscription->tenant;
        $plan = $subscription->plan;
        $resumedSubscription = $this->performResume($subscription, $tenant);

        // Send notification
        $this->sendResumedNotification($tenant, $plan);

        return $resumedSubscription;
    }

    private function performResume(Subscription $subscription, Tenant $tenant): Subscription
    {
        return DB::transaction(function () use ($subscription, $tenant): Subscription {
            // For free plan subscriptions, just clear ends_at
            if (str_starts_with($subscription->stripe_id, 'free_')) {
                return $this->subscriptions->update($subscription, [
                    'ends_at' => null,
                    'stripe_status' => SubscriptionStatus::Active->value,
                ]);
            }

            // Resume in Stripe
            $stripeSub = $tenant->subscription(SubscriptionType::Default->value);

            if (! $stripeSub) {
                throw SubscriptionException::noActiveSubscription();
            }

            $stripeSub->resume();

            // Update local record
            return $this->subscriptions->update($subscription, [
                'ends_at' => null,
            ]);
        });
    }

    private function sendResumedNotification(Tenant $tenant, \App\Models\Plan $plan): void
    {
        $owner = $tenant->owner;

        if (! $owner) {
            return;
        }

        $owner->notify(new SubscriptionResumedNotification($tenant, $plan));
    }
}
