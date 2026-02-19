<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Services\FeatureGateServiceContract;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Enums\Feature;
use App\Exceptions\FeatureNotAvailableException;
use App\Models\Plan;
use App\Models\Tenant;

final class FeatureGateService implements FeatureGateServiceContract
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly PlanRepository $plans,
    ) {}

    /**
     * Check if a tenant has access to a specific feature.
     */
    public function canAccess(Tenant $tenant, string $feature): bool
    {
        return $this->subscriptionService->hasFeature($tenant, $feature);
    }

    /**
     * Check if a tenant has access to a specific Feature enum.
     */
    public function canAccessFeature(Tenant $tenant, Feature $feature): bool
    {
        return $this->canAccess($tenant, $feature->value);
    }

    /**
     * Get the minimum plan required for a feature.
     */
    public function getRequiredPlan(string $feature): ?Plan
    {
        $featureEnum = Feature::tryFromString($feature);

        if (! $featureEnum) {
            return null;
        }

        $requiredPlanSlug = $featureEnum->getMinimumPlan();

        return $this->plans->findBySlug($requiredPlanSlug);
    }

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
    public function buildUpgradeMessage(string $feature, ?string $currentPlan = null): array
    {
        $requiredPlan = $this->getRequiredPlan($feature);
        $featureEnum = Feature::tryFromString($feature);

        $featureLabel = $featureEnum ? $featureEnum->getLabel() : $feature;
        $requiredPlanName = $requiredPlan ? $requiredPlan->name : 'a higher';
        $requiredPlanSlug = $requiredPlan ? $requiredPlan->slug : 'premium';
        $requiredPlanPrice = $requiredPlan?->monthly_price;

        return [
            'error' => 'feature_not_available',
            'message' => "The '{$featureLabel}' feature requires {$requiredPlanName} plan or higher.",
            'feature' => $feature,
            'current_plan' => $currentPlan,
            'required_plan' => [
                'name' => $requiredPlan?->name,
                'slug' => $requiredPlanSlug,
                'monthly_price' => $requiredPlanPrice,
            ],
            'upgrade_url' => '/billing/upgrade',
        ];
    }

    /**
     * Authorize access to a feature, throwing an exception if not allowed.
     *
     * @throws FeatureNotAvailableException
     */
    public function authorize(Tenant $tenant, string $feature): void
    {
        if (! $this->canAccess($tenant, $feature)) {
            $featureEnum = Feature::tryFromString($feature);

            if ($featureEnum) {
                throw FeatureNotAvailableException::forFeature($featureEnum);
            }

            throw new FeatureNotAvailableException($feature);
        }
    }

    /**
     * Get the feature value for tiered features.
     */
    public function getFeatureValue(Tenant $tenant, string $feature): mixed
    {
        return $this->subscriptionService->getFeatureValue($tenant, $feature);
    }
}
