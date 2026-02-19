<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\UsageResource;
use App\Models\Tenant;

interface UsageLimitServiceContract
{
    public function canUseResource(Tenant $tenant, UsageResource $resource): bool;

    /**
     * Get current usage vs limits for all resources.
     *
     * @return array<string, array{current: int, limit: int|string, percentage: float}>
     */
    public function getUsageStats(Tenant $tenant): array;

    /**
     * Get usage percentage for a specific resource.
     */
    public function getUsagePercentage(Tenant $tenant, UsageResource $resource): float;

    /**
     * Check if tenant is near the limit (>= 80%).
     */
    public function isNearLimit(Tenant $tenant, UsageResource $resource): bool;

    /**
     * Check if tenant has reached the limit (>= 100%).
     */
    public function hasReachedLimit(Tenant $tenant, UsageResource $resource): bool;

    /**
     * Record that a reservation was created.
     */
    public function recordReservationCreated(Tenant $tenant): void;

    /**
     * Record that a reservation was deleted/cancelled.
     */
    public function recordReservationDeleted(Tenant $tenant): void;
}
