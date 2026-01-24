<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\DTOs\Subscription\DowngradeSubscriptionDTO;
use App\Enums\SubscriptionType;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionDowngradeScheduledNotification;
use App\Services\Subscription\UsageValidationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class SubscriptionDowngradeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly UsageValidationService $usageValidation,
    ) {}

    public function handle(DowngradeSubscriptionDTO $dto): Subscription
    {
        $subscription = $this->subscriptions->findById($dto->subscriptionId);

        if (! $subscription) {
            throw SubscriptionException::subscriptionNotFound($dto->subscriptionId);
        }

        $newPlan = $this->plans->findById($dto->newPlanId);

        if (! $newPlan) {
            throw SubscriptionException::planNotFound($dto->newPlanId);
        }

        $tenant = $subscription->tenant;
        $currentPlan = $subscription->plan;

        if (! $this->subscriptionService->canDowngradeTo($tenant, $newPlan)) {
            throw SubscriptionException::cannotDowngrade($currentPlan, $newPlan);
        }

        // Check if current usage exceeds new plan limits
        $violations = $this->usageValidation->checkLimitViolations($tenant, $newPlan);

        if (! empty($violations)) {
            throw SubscriptionException::usageExceedsLimits($violations);
        }

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
