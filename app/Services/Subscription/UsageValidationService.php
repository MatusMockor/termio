<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Services\UsageValidationServiceContract;
use App\Enums\UsageResource;
use App\Models\Plan;
use App\Models\Tenant;

final class UsageValidationService implements UsageValidationServiceContract
{
    /**
     * Check if tenant's current usage exceeds new plan limits.
     *
     * @return array<string, array{current: int, limit: int}>
     */
    public function checkLimitViolations(Tenant $tenant, Plan $newPlan): array
    {
        $violations = [];
        $limits = $newPlan->limits;

        foreach (UsageResource::planValidationResources() as $resource) {
            $current = $this->getCurrentResourceCount($tenant, $resource);
            $limit = (int) ($limits[$resource->planLimitKey()] ?? $resource->defaultLimit());

            if ($limit !== -1 && $current > $limit) {
                $violations[$resource->value] = [
                    'current' => $current,
                    'limit' => $limit,
                ];
            }
        }

        return $violations;
    }

    /**
     * Check if a specific resource can be added based on current plan limits.
     */
    public function canAddResource(Tenant $tenant, Plan $plan, UsageResource $resource): bool
    {
        $limits = $plan->limits;
        $limit = (int) ($limits[$resource->planLimitKey()] ?? $resource->defaultLimit());

        // Unlimited
        if ($limit === -1) {
            return true;
        }

        $currentCount = $this->getCurrentResourceCount($tenant, $resource);

        return $currentCount < $limit;
    }

    /**
     * Get remaining capacity for a specific resource.
     */
    public function getRemainingCapacity(Tenant $tenant, Plan $plan, UsageResource $resource): int
    {
        $limits = $plan->limits;
        $limit = (int) ($limits[$resource->planLimitKey()] ?? $resource->defaultLimit());

        // Unlimited
        if ($limit === -1) {
            return -1;
        }

        $currentCount = $this->getCurrentResourceCount($tenant, $resource);

        return max(0, $limit - $currentCount);
    }

    /**
     * Get current count for a specific resource.
     */
    private function getCurrentResourceCount(Tenant $tenant, UsageResource $resource): int
    {
        return match ($resource) {
            UsageResource::Users => $tenant->users()->count(),
            UsageResource::Services => $tenant->services()->count(),
            UsageResource::Clients => $tenant->clients()->count(),
            UsageResource::Reservations => 0,
        };
    }
}
