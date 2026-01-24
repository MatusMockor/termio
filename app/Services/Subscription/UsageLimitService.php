<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Repositories\UsageRecordRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\UsageLimitServiceContract;
use App\Models\Tenant;
use RuntimeException;

final class UsageLimitService implements UsageLimitServiceContract
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly UsageRecordRepository $usageRecords,
    ) {}

    /**
     * Check if tenant can create a new reservation.
     */
    public function canCreateReservation(Tenant $tenant): bool
    {
        try {
            if ($this->subscriptionService->isUnlimited($tenant, 'reservations_per_month')) {
                return true;
            }

            $limit = $this->subscriptionService->getLimit($tenant, 'reservations_per_month');
        } catch (RuntimeException) {
            $limit = config('subscription.default_limits.reservations');
        }

        $usage = $this->usageRecords->getCurrentUsage($tenant);

        return $usage->reservations_count < $limit;
    }

    /**
     * Check if tenant can add a new user (staff).
     */
    public function canAddUser(Tenant $tenant): bool
    {
        try {
            if ($this->subscriptionService->isUnlimited($tenant, 'users')) {
                return true;
            }

            $limit = $this->subscriptionService->getLimit($tenant, 'users');
        } catch (RuntimeException) {
            $limit = config('subscription.default_limits.users');
        }

        $currentCount = $tenant->users()->count();

        return $currentCount < $limit;
    }

    /**
     * Check if tenant can add a new service.
     */
    public function canAddService(Tenant $tenant): bool
    {
        try {
            if ($this->subscriptionService->isUnlimited($tenant, 'services')) {
                return true;
            }

            $limit = $this->subscriptionService->getLimit($tenant, 'services');
        } catch (RuntimeException) {
            $limit = config('subscription.default_limits.services');
        }

        $currentCount = $tenant->services()->count();

        return $currentCount < $limit;
    }

    /**
     * Get current usage vs limits for all resources.
     *
     * @return array<string, array{current: int, limit: int|string, percentage: float}>
     */
    public function getUsageStats(Tenant $tenant): array
    {
        $usage = $this->usageRecords->getCurrentUsage($tenant);

        $reservationsLimit = $this->getLimit($tenant, 'reservations_per_month', config('subscription.default_limits.reservations'));
        $usersLimit = $this->getLimit($tenant, 'users', config('subscription.default_limits.users'));
        $servicesLimit = $this->getLimit($tenant, 'services', config('subscription.default_limits.services'));

        $userCount = $tenant->users()->count();
        $serviceCount = $tenant->services()->count();

        return [
            'reservations' => [
                'current' => $usage->reservations_count,
                'limit' => $reservationsLimit === -1 ? 'unlimited' : $reservationsLimit,
                'percentage' => $this->calculatePercentage($usage->reservations_count, $reservationsLimit),
            ],
            'users' => [
                'current' => $userCount,
                'limit' => $usersLimit === -1 ? 'unlimited' : $usersLimit,
                'percentage' => $this->calculatePercentage($userCount, $usersLimit),
            ],
            'services' => [
                'current' => $serviceCount,
                'limit' => $servicesLimit === -1 ? 'unlimited' : $servicesLimit,
                'percentage' => $this->calculatePercentage($serviceCount, $servicesLimit),
            ],
        ];
    }

    /**
     * Get limit for a resource with fallback to default.
     */
    private function getLimit(Tenant $tenant, string $resource, int $default): int
    {
        try {
            return $this->subscriptionService->getLimit($tenant, $resource);
        } catch (RuntimeException) {
            return $default;
        }
    }

    /**
     * Get usage percentage for a specific resource.
     */
    public function getUsagePercentage(Tenant $tenant, string $resource): float
    {
        $stats = $this->getUsageStats($tenant);

        if (! isset($stats[$resource])) {
            return 0.0;
        }

        return $stats[$resource]['percentage'];
    }

    /**
     * Check if tenant is near the limit (>= 80%).
     */
    public function isNearLimit(Tenant $tenant, string $resource): bool
    {
        $percentage = $this->getUsagePercentage($tenant, $resource);

        return $percentage >= (config('subscription.usage_warning_threshold') * 100) && $percentage < 100;
    }

    /**
     * Check if tenant has reached the limit (>= 100%).
     */
    public function hasReachedLimit(Tenant $tenant, string $resource): bool
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

    /**
     * Calculate percentage of usage.
     */
    private function calculatePercentage(int $current, int $limit): float
    {
        if ($limit === -1) {
            return 0.0;
        }

        if ($limit === 0) {
            return 100.0;
        }

        return min(100.0, ($current / $limit) * 100);
    }
}
