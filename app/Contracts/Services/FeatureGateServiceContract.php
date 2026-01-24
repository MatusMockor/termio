<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

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
     * Deny access and return a JSON response with upgrade information.
     */
    public function denyWithUpgradeMessage(string $feature, ?string $currentPlan = null): JsonResponse;

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
