<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionUpgradeBillingServiceContract;
use App\DTOs\Subscription\DowngradeSubscriptionDTO;
use App\DTOs\Subscription\ValidationContext;
use App\Enums\BillingCycle;
use App\Enums\PlanSlug;
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
        private readonly SubscriptionUpgradeBillingServiceContract $upgradeBillingService,
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
            if ($subscription->onTrial() && $newPlan->slug !== PlanSlug::Free->value) {
                return $this->applyImmediateTrialDowngrade($subscription, $newPlan);
            }

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
            $periodEnd = $this->resolvePeriodEnd($stripeSubscription);

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

    /**
     * @return array{subscription: Subscription, effective_date: Carbon}
     */
    private function applyImmediateTrialDowngrade(Subscription $subscription, Plan $newPlan): array
    {
        $priceId = $this->upgradeBillingService->resolvePriceId(
            $newPlan,
            BillingCycle::from($subscription->billing_cycle),
        );
        $this->upgradeBillingService->swapPaidSubscription($subscription, $priceId);

        $effectiveDate = now();

        return [
            'subscription' => $this->subscriptions->update($subscription, [
                'plan_id' => $newPlan->id,
                'stripe_price' => $priceId,
                'scheduled_plan_id' => null,
                'scheduled_change_at' => null,
            ]),
            'effective_date' => $effectiveDate,
        ];
    }

    private function resolvePeriodEnd(object $stripeSubscription): Carbon
    {
        $currentPeriodEnd = $this->toTimestamp(
            isset($stripeSubscription->current_period_end) ? $stripeSubscription->current_period_end : null
        );

        if ($currentPeriodEnd !== null) {
            return Carbon::createFromTimestamp($currentPeriodEnd);
        }

        $trialEnd = $this->toTimestamp(
            isset($stripeSubscription->trial_end) ? $stripeSubscription->trial_end : null
        );

        if ($trialEnd !== null) {
            return Carbon::createFromTimestamp($trialEnd);
        }

        throw SubscriptionException::stripeError('Unable to determine subscription period end from Stripe response.');
    }

    private function toTimestamp(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
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
