<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;

final class ProrationService
{
    private const DAYS_IN_YEAR = 365;

    private const DAYS_IN_MONTH = 30;

    /**
     * Calculate proration for plan upgrade.
     *
     * @return array{credit: float, charge: float, total: float, days_remaining: int}
     */
    public function calculateUpgradeProration(
        Subscription $subscription,
        Plan $newPlan,
        string $newBillingCycle
    ): array {
        $currentPlan = $subscription->plan;
        $billingCycle = $subscription->billing_cycle;

        // Get current period dates from Stripe
        $stripeSub = $subscription->tenant->subscription('default');

        if (! $stripeSub) {
            return [
                'credit' => 0.0,
                'charge' => 0.0,
                'total' => 0.0,
                'days_remaining' => 0,
            ];
        }

        $stripeSubscription = $stripeSub->asStripeSubscription();
        /** @var int $currentPeriodEnd */
        $currentPeriodEnd = $stripeSubscription->current_period_end;
        $periodEnd = Carbon::createFromTimestamp($currentPeriodEnd);

        $daysRemaining = (int) now()->diffInDays($periodEnd, false);
        $daysRemaining = max(0, $daysRemaining);

        // Calculate daily rates
        $currentPrice = $billingCycle === 'yearly'
            ? (float) $currentPlan->yearly_price / self::DAYS_IN_YEAR
            : (float) $currentPlan->monthly_price / self::DAYS_IN_MONTH;

        $newPrice = $newBillingCycle === 'yearly'
            ? (float) $newPlan->yearly_price / self::DAYS_IN_YEAR
            : (float) $newPlan->monthly_price / self::DAYS_IN_MONTH;

        // Calculate proration
        $credit = round($currentPrice * $daysRemaining, 2);
        $charge = round($newPrice * $daysRemaining, 2);
        $total = round($charge - $credit, 2);

        return [
            'credit' => $credit,
            'charge' => $charge,
            'total' => max(0.0, $total), // Never negative for upgrades
            'days_remaining' => $daysRemaining,
        ];
    }

    /**
     * Calculate proration preview for display purposes.
     *
     * @return array{credit: float, charge: float, total: float, days_remaining: int, effective_date: Carbon}
     */
    public function calculateProrationPreview(
        Subscription $subscription,
        Plan $newPlan,
        string $newBillingCycle
    ): array {
        $proration = $this->calculateUpgradeProration($subscription, $newPlan, $newBillingCycle);

        return [
            ...$proration,
            'effective_date' => now(),
        ];
    }
}
