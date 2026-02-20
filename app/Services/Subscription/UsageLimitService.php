<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Repositories\UsageRecordRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\UsageLimitServiceContract;
use App\Enums\UsageResource;
use App\Models\Tenant;
use App\Models\UsageRecord;
use RuntimeException;

final class UsageLimitService implements UsageLimitServiceContract
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly UsageRecordRepository $usageRecords,
    ) {}

    public function canUseResource(Tenant $tenant, UsageResource $resource): bool
    {
        $limit = $this->resolveLimit($tenant, $resource);

        if ($this->isUnlimitedLimit($limit)) {
            return true;
        }

        return $this->getCurrentCount($tenant, $resource) < $limit;
    }

    /**
     * Get current usage vs limits for all resources.
     *
     * @return array<string, array{current: int, limit: int|string, percentage: float}>
     */
    public function getUsageStats(Tenant $tenant): array
    {
        $usage = $this->usageRecords->getCurrentUsage($tenant);
        $stats = [];

        foreach (UsageResource::usageStatsResources() as $resource) {
            $current = $this->getCurrentCount($tenant, $resource, $usage);
            $limit = $this->resolveLimit($tenant, $resource);

            $stats[$resource->value] = [
                'current' => $current,
                'limit' => $this->isUnlimitedLimit($limit) ? 'unlimited' : $limit,
                'percentage' => $this->calculatePercentage($current, $limit),
            ];
        }

        return $stats;
    }

    /**
     * Get usage percentage for a specific resource.
     */
    public function getUsagePercentage(Tenant $tenant, UsageResource $resource): float
    {
        $limit = $this->resolveLimit($tenant, $resource);
        $current = $this->getCurrentCount($tenant, $resource);

        return $this->calculatePercentage($current, $limit);
    }

    /**
     * Check if tenant is near the limit (>= 80%).
     */
    public function isNearLimit(Tenant $tenant, UsageResource $resource): bool
    {
        $percentage = $this->getUsagePercentage($tenant, $resource);

        return $percentage >= (config('subscription.usage_warning_threshold') * 100) && $percentage < 100;
    }

    /**
     * Check if tenant has reached the limit (>= 100%).
     */
    public function hasReachedLimit(Tenant $tenant, UsageResource $resource): bool
    {
        $percentage = $this->getUsagePercentage($tenant, $resource);

        return $percentage >= 100;
    }

    /**
     * Record that a reservation was created.
     */
    public function recordReservationCreated(Tenant $tenant): void
    {
        $this->usageRecords->incrementReservations($tenant);
    }

    /**
     * Record that a reservation was deleted/cancelled.
     */
    public function recordReservationDeleted(Tenant $tenant): void
    {
        $this->usageRecords->decrementReservations($tenant);
    }

    private function resolveLimit(Tenant $tenant, UsageResource $resource): int
    {
        try {
            if ($this->subscriptionService->isUnlimited($tenant, $resource->planLimitKey())) {
                return $this->unlimitedLimit();
            }

            return $this->subscriptionService->getLimit($tenant, $resource->planLimitKey());
        } catch (RuntimeException) {
            return $resource->defaultLimit();
        }
    }

    private function getCurrentCount(Tenant $tenant, UsageResource $resource, ?UsageRecord $usage = null): int
    {
        if ($resource === UsageResource::Reservations) {
            if ($usage !== null) {
                return $usage->reservations_count;
            }

            return $this->usageRecords->getCurrentUsage($tenant)->reservations_count;
        }

        return match ($resource) {
            UsageResource::Users => $tenant->users()->count(),
            UsageResource::Services => $tenant->services()->count(),
            UsageResource::Clients => $tenant->clients()->count(),
        };
    }

    /**
     * Calculate percentage of usage.
     */
    private function calculatePercentage(int $current, int $limit): float
    {
        if ($this->isUnlimitedLimit($limit)) {
            return 0.0;
        }

        if ($limit === 0) {
            return 100.0;
        }

        return min(100.0, ($current / $limit) * 100);
    }

    private function isUnlimitedLimit(int $limit): bool
    {
        return $limit === $this->unlimitedLimit();
    }

    private function unlimitedLimit(): int
    {
        return (int) config('subscription.unlimited', -1);
    }
}
