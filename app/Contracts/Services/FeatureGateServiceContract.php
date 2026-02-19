<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Tenant;

interface FeatureGateServiceContract
{
    /**
     * Check if a tenant has access to a specific feature.
     */
    public function canAccess(Tenant $tenant, string $feature): bool;

    /**
     * Check if a tenant has access to a specific Feature enum.
     */
    public function canAccessFeature(Tenant $tenant, Feature $feature): bool;

    /**
     * Get the minimum plan required for a feature.
     */
    public function getRequiredPlan(string $feature): ?Plan;

    /**
     * Build feature access denial payload for API callers.
     *
     * @return array{
     *     error: string,
     *     message: string,
     *     feature: string,
     *     current_plan: string|null,
     *     required_plan: array{name: string|null, slug: string, monthly_price: string|null},
     *     upgrade_url: string
     * }
     */
    public function buildUpgradeMessage(string $feature, ?string $currentPlan = null): array;

    /**
     * Authorize access to a feature, throwing an exception if not allowed.
     *
     * @throws \App\Exceptions\FeatureNotAvailableException
     */
    public function authorize(Tenant $tenant, string $feature): void;

    /**
     * Get the feature value for tiered features.
     */
    public function getFeatureValue(Tenant $tenant, string $feature): mixed;
}
