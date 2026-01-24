<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Tenant;

final class FeatureListService
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly PlanRepository $plans,
    ) {}

    /**
     * Get all features with their availability status for a tenant.
     *
     * @return array<string, array{available: bool, value: mixed, required_plan: string, label: string, category: string}>
     */
    public function getFeatureStatus(Tenant $tenant): array
    {
        $result = [];

        foreach (Feature::cases() as $feature) {
            $result[$feature->value] = [
                'available' => $this->subscriptionService->hasFeature($tenant, $feature->value),
                'value' => $this->subscriptionService->getFeatureValue($tenant, $feature->value),
                'required_plan' => $feature->getMinimumPlan(),
                'label' => $feature->getLabel(),
                'category' => $feature->getCategory(),
            ];
        }

        return $result;
    }

    /**
     * Get status for a single feature.
     *
     * @return array{available: bool, value: mixed, required_plan: array{name: string|null, slug: string, monthly_price: string|null}, label: string, category: string}|null
     */
    public function getSingleFeatureStatus(Tenant $tenant, string $featureKey): ?array
    {
        $feature = Feature::tryFromString($featureKey);

        if (! $feature) {
            return null;
        }

        $requiredPlan = $this->plans->findBySlug($feature->getMinimumPlan());

        return [
            'available' => $this->subscriptionService->hasFeature($tenant, $feature->value),
            'value' => $this->subscriptionService->getFeatureValue($tenant, $feature->value),
            'required_plan' => [
                'name' => $requiredPlan?->name,
                'slug' => $feature->getMinimumPlan(),
                'monthly_price' => $requiredPlan?->monthly_price,
            ],
            'label' => $feature->getLabel(),
            'category' => $feature->getCategory(),
        ];
    }

    /**
     * Get all available features grouped by category.
     *
     * @return array<string, array<string, array{required_plan: string, label: string}>>
     */
    public function getFeaturesGrouped(): array
    {
        $grouped = [];

        foreach (Feature::cases() as $feature) {
            $category = $feature->getCategory();

            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }

            $grouped[$category][$feature->value] = [
                'required_plan' => $feature->getMinimumPlan(),
                'label' => $feature->getLabel(),
            ];
        }

        return $grouped;
    }

    /**
     * Get features available in a specific plan.
     *
     * @return array<string, array{label: string, category: string}>
     */
    public function getFeaturesForPlan(Plan $plan): array
    {
        $planFeatures = $plan->features;
        $result = [];

        foreach (Feature::cases() as $feature) {
            $featureValue = $planFeatures[$feature->value] ?? null;

            // Include feature if it's explicitly enabled in the plan
            if ($this->isFeatureEnabled($featureValue)) {
                $result[$feature->value] = [
                    'label' => $feature->getLabel(),
                    'category' => $feature->getCategory(),
                ];
            }
        }

        return $result;
    }

    /**
     * Compare features between two plans.
     *
     * @return array{added: array<string>, removed: array<string>}
     */
    public function compareFeatures(Plan $fromPlan, Plan $toPlan): array
    {
        $fromFeatures = $this->getFeaturesForPlan($fromPlan);
        $toFeatures = $this->getFeaturesForPlan($toPlan);

        $fromKeys = array_keys($fromFeatures);
        $toKeys = array_keys($toFeatures);

        return [
            'added' => array_values(array_diff($toKeys, $fromKeys)),
            'removed' => array_values(array_diff($fromKeys, $toKeys)),
        ];
    }

    /**
     * Check if a feature value indicates the feature is enabled.
     */
    private function isFeatureEnabled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        // String values like 'basic', 'advanced' mean enabled
        // Only 'none' or empty string means disabled
        if (is_string($value)) {
            return $value !== 'none' && $value !== '';
        }

        return (bool) $value;
    }
}
