<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\DTOs\Subscription\DowngradeSubscriptionDTO;
use App\DTOs\Subscription\ValidationContext;
use App\Enums\SubscriptionType;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionDowngradeScheduledNotification;
use App\Services\Validation\ValidationChainBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class SubscriptionDowngradeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly ValidationChainBuilder $validationChainBuilder,
    ) {}

    public function handle(DowngradeSubscriptionDTO $dto): Subscription
    {
        $subscription = $this->subscriptions->findById($dto->subscriptionId);
        $newPlan = $this->plans->findById($dto->newPlanId);

        // Build and run validation chain
        $validationChain = $this->validationChainBuilder->buildDowngradeChain();
        $context = ValidationContext::forDowngrade(
            subscription: $subscription,
            newPlan: $newPlan,
            subscriptionId: $dto->subscriptionId,
            planId: $dto->newPlanId,
        );
        $validationChain->validate($context);

        /** @var Subscription $subscription */
        /** @var Plan $newPlan */
        $tenant = $subscription->tenant;
        $currentPlan = $subscription->plan;

        $result = $this->scheduleDowngrade($subscription, $newPlan);

        // Send notification
        $this->sendDowngradeScheduledNotification($tenant, $currentPlan, $newPlan, $result['effective_date']);

        return $result['subscription'];
    }

    /**
     * @return array{subscription: Subscription, effective_date: Carbon}
     */
    private function scheduleDowngrade(Subscription $subscription, Plan $newPlan): array
    {
        return DB::transaction(function () use ($subscription, $newPlan): array {
            // For free plan subscriptions, schedule change immediately
            if (str_starts_with($subscription->stripe_id, 'free_')) {
                $effectiveDate = now();

                return [
                    'subscription' => $this->subscriptions->update($subscription, [
                        'scheduled_plan_id' => $newPlan->id,
                        'scheduled_change_at' => $effectiveDate,
                    ]),
                    'effective_date' => $effectiveDate,
                ];
            }

            // Get current period end from Stripe
            $stripeSub = $subscription->tenant->subscription(SubscriptionType::Default->value);

            if (! $stripeSub) {
                throw SubscriptionException::noActiveSubscription();
            }

            $stripeSubscription = $stripeSub->asStripeSubscription();
            /** @var int $currentPeriodEnd */
            $currentPeriodEnd = $stripeSubscription->current_period_end;
            $periodEnd = Carbon::createFromTimestamp($currentPeriodEnd);

            // Schedule downgrade for end of current period
            return [
                'subscription' => $this->subscriptions->update($subscription, [
                    'scheduled_plan_id' => $newPlan->id,
                    'scheduled_change_at' => $periodEnd,
                ]),
                'effective_date' => $periodEnd,
            ];
        });
    }

    private function sendDowngradeScheduledNotification(
        Tenant $tenant,
        Plan $currentPlan,
        Plan $newPlan,
        Carbon $effectiveDate,
    ): void {
        $owner = $tenant->owner;

        if (! $owner) {
            return;
        }

        $owner->notify(new SubscriptionDowngradeScheduledNotification(
            $tenant,
            $currentPlan,
            $newPlan,
            $effectiveDate,
        ));
    }
}
