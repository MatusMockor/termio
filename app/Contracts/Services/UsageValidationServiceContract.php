<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\UsageResource;
use App\Models\Plan;
use App\Models\Tenant;

interface UsageValidationServiceContract
{
    /**
     * Check if tenant's current usage exceeds new plan limits.
     *
     * @return array<string, array{current: int, limit: int}>
     */
    public function checkLimitViolations(Tenant $tenant, Plan $newPlan): array;

    /**
     * Check if a specific resource can be added based on current plan limits.
     */
    public function canAddResource(Tenant $tenant, Plan $plan, UsageResource $resource): bool;

    /**
     * Get remaining capacity for a specific resource.
     */
    public function getRemainingCapacity(Tenant $tenant, Plan $plan, UsageResource $resource): int;
}
