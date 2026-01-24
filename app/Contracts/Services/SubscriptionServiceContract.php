<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Plan;
use App\Models\Tenant;
use Carbon\Carbon;

interface SubscriptionServiceContract
{
    /**
     * Get the current active plan for a tenant.
     */
    public function getCurrentPlan(Tenant $tenant): Plan;

    /**
     * Check if tenant has access to a specific feature.
     */
    public function hasFeature(Tenant $tenant, string $feature): bool;

    /**
     * Get feature value (for tiered features like 'basic' vs 'advanced').
     */
    public function getFeatureValue(Tenant $tenant, string $feature): mixed;

    /**
     * Get usage limit for a specific resource.
     */
    public function getLimit(Tenant $tenant, string $resource): int;

    /**
     * Check if limit is unlimited (-1).
     */
    public function isUnlimited(Tenant $tenant, string $resource): bool;

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(Tenant $tenant): bool;

    /**
     * Get trial days remaining.
     */
    public function getTrialDaysRemaining(Tenant $tenant): int;

    /**
     * Check if tenant can upgrade to a specific plan.
     */
    public function canUpgradeTo(Tenant $tenant, Plan $newPlan): bool;

    /**
     * Check if tenant can downgrade to a specific plan.
     */
    public function canDowngradeTo(Tenant $tenant, Plan $newPlan): bool;

    /**
     * Get plans available for upgrade.
     *
     * @return array<int, Plan>
     */
    public function getUpgradeOptions(Tenant $tenant): array;

    /**
     * Get plans available for downgrade.
     *
     * @return array<int, Plan>
     */
    public function getDowngradeOptions(Tenant $tenant): array;

    /**
     * Check if a scheduled downgrade or cancellation is pending.
     */
    public function hasPendingChange(Tenant $tenant): bool;

    /**
     * Get pending change details.
     *
     * @return array{type: string, plan: ?Plan, date: ?Carbon}|null
     */
    public function getPendingChange(Tenant $tenant): ?array;
}
