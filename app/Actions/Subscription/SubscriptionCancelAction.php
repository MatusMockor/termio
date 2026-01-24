<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionCanceledNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class SubscriptionCancelAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handle(int $subscriptionId, ?string $reason = null): Subscription
    {
        $subscription = $this->subscriptions->findById($subscriptionId);

        if (! $subscription) {
            throw SubscriptionException::subscriptionNotFound($subscriptionId);
        }

        if ($subscription->stripe_status === 'canceled') {
            throw SubscriptionException::alreadyCanceled();
        }

        $tenant = $subscription->tenant;
        $result = $this->performCancellation($subscription, $tenant);

        // Send notification
        $this->sendCanceledNotification($tenant, $result['ends_at']);

        return $result['subscription'];
    }

    /**
     * @return array{subscription: Subscription, ends_at: Carbon}
     */
    private function performCancellation(Subscription $subscription, Tenant $tenant): array
    {
        return DB::transaction(function () use ($subscription, $tenant): array {
            // For free plan subscriptions, cancel immediately
            if (str_starts_with($subscription->stripe_id, 'free_')) {
                $endsAt = now();

                return [
                    'subscription' => $this->subscriptions->update($subscription, [
                        'ends_at' => $endsAt,
                        'stripe_status' => 'canceled',
                        'scheduled_plan_id' => null,
                        'scheduled_change_at' => null,
                    ]),
                    'ends_at' => $endsAt,
                ];
            }

            // Cancel at period end in Stripe
            $stripeSub = $tenant->subscription('default');

            if (! $stripeSub) {
                throw SubscriptionException::noActiveSubscription();
            }

            $stripeSub->cancel();

            // Get period end date
            $stripeSubscription = $stripeSub->asStripeSubscription();
            /** @var int $currentPeriodEnd */
            $currentPeriodEnd = $stripeSubscription->current_period_end;
            $endsAt = Carbon::createFromTimestamp($currentPeriodEnd);

            // Update local record
            return [
                'subscription' => $this->subscriptions->update($subscription, [
                    'ends_at' => $endsAt,
                    'scheduled_plan_id' => null,
                    'scheduled_change_at' => null,
                ]),
                'ends_at' => $endsAt,
            ];
        });
    }

    private function sendCanceledNotification(Tenant $tenant, Carbon $endsAt): void
    {
        $owner = $tenant->owner;

        if (! $owner) {
            return;
        }

        $owner->notify(new SubscriptionCanceledNotification($tenant, $endsAt));
    }
}
