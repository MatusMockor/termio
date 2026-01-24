<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Contracts\Repositories\PlanRepository;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

final class PlanStatisticsService
{
    private const int MONTHS_PER_YEAR = 12;

    public function __construct(
        private readonly PlanRepository $planRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $plans = $this->planRepository->getAll();
        $totalSubscribers = $this->getTotalActiveSubscribers();
        $planStats = [];
        $mrr = 0.0;

        foreach ($plans as $plan) {
            $subscriberCount = $this->planRepository->getSubscriberCount($plan);
            $planMrr = $this->calculatePlanMrr($plan);
            $mrr += $planMrr;

            $percentage = $totalSubscribers > 0
                ? round(($subscriberCount / $totalSubscribers) * 100, 1)
                : 0.0;

            $planStats[] = [
                'id' => $plan->id,
                'name' => $plan->name,
                'subscriber_count' => $subscriberCount,
                'percentage' => $percentage,
                'mrr' => round($planMrr, 2),
            ];
        }

        return [
            'total_subscribers' => $totalSubscribers,
            'mrr' => round($mrr, 2),
            'arr' => round($mrr * self::MONTHS_PER_YEAR, 2),
            'plans' => $planStats,
            'churn_rate' => $this->calculateChurnRate(),
            'trial_conversion_rate' => $this->calculateTrialConversionRate(),
        ];
    }

    private function getTotalActiveSubscribers(): int
    {
        return Subscription::where('stripe_status', 'active')
            ->whereNull('ends_at')
            ->count();
    }

    private function calculatePlanMrr(Plan $plan): float
    {
        $monthlySubscriptions = Subscription::where('plan_id', $plan->id)
            ->where('stripe_status', 'active')
            ->whereNull('ends_at')
            ->where('billing_cycle', 'monthly')
            ->count();

        $yearlySubscriptions = Subscription::where('plan_id', $plan->id)
            ->where('stripe_status', 'active')
            ->whereNull('ends_at')
            ->where('billing_cycle', 'yearly')
            ->count();

        $monthlyRevenue = $monthlySubscriptions * (float) $plan->monthly_price;
        $yearlyMonthlyEquivalent = ($yearlySubscriptions * (float) $plan->yearly_price) / self::MONTHS_PER_YEAR;

        return $monthlyRevenue + $yearlyMonthlyEquivalent;
    }

    private function calculateChurnRate(): float
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $startingSubscribers = Subscription::where('stripe_status', 'active')
            ->where('created_at', '<', $thirtyDaysAgo)
            ->count();

        if ($startingSubscribers === 0) {
            return 0.0;
        }

        $canceledInPeriod = Subscription::whereNotNull('ends_at')
            ->where('ends_at', '>=', $thirtyDaysAgo)
            ->where('ends_at', '<=', Carbon::now())
            ->count();

        return round(($canceledInPeriod / $startingSubscribers) * 100, 2);
    }

    private function calculateTrialConversionRate(): float
    {
        $completedTrials = Subscription::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', Carbon::now())
            ->count();

        if ($completedTrials === 0) {
            return 0.0;
        }

        $convertedTrials = Subscription::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', Carbon::now())
            ->where('stripe_status', 'active')
            ->count();

        return round(($convertedTrials / $completedTrials) * 100, 2);
    }
}
