<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Services\UsageValidationServiceContract;
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

        // Check users limit
        $userCount = $tenant->users()->count();
        $userLimit = $limits['users'] ?? 1;

        if ($userLimit !== -1 && $userCount > $userLimit) {
            $violations['users'] = [
                'current' => $userCount,
                'limit' => $userLimit,
            ];
        }

        // Check services limit
        $serviceCount = $tenant->services()->count();
        $serviceLimit = $limits['services'] ?? 10;

        if ($serviceLimit !== -1 && $serviceCount > $serviceLimit) {
            $violations['services'] = [
                'current' => $serviceCount,
                'limit' => $serviceLimit,
            ];
        }

        // Check clients limit
        $clientCount = $tenant->clients()->count();
        $clientLimit = $limits['clients'] ?? 100;

        if ($clientLimit !== -1 && $clientCount > $clientLimit) {
            $violations['clients'] = [
                'current' => $clientCount,
                'limit' => $clientLimit,
            ];
        }

        return $violations;
    }

    /**
     * Check if a specific resource can be added based on current plan limits.
     */
    public function canAddResource(Tenant $tenant, Plan $plan, string $resource): bool
    {
        $limits = $plan->limits;
        $limit = $limits[$resource] ?? 0;

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
    public function getRemainingCapacity(Tenant $tenant, Plan $plan, string $resource): int
    {
        $limits = $plan->limits;
        $limit = $limits[$resource] ?? 0;

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
    private function getCurrentResourceCount(Tenant $tenant, string $resource): int
    {
        return match ($resource) {
            'users' => $tenant->users()->count(),
            'services' => $tenant->services()->count(),
            'clients' => $tenant->clients()->count(),
            default => 0,
        };
    }
}
