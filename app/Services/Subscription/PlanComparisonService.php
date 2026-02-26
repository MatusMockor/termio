<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\DTOs\Subscription\PlanComparisonMatrixDTO;
use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Support\Collection;

final class PlanComparisonService
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {}

    /**
     * Get full comparison matrix of all public plans with features.
     */
    public function getComparisonMatrix(): PlanComparisonMatrixDTO
    {
        $plans = $this->plans->getPublic();
        $features = $this->buildFeatureMatrix($plans);

        return new PlanComparisonMatrixDTO(
            plans: $plans,
            features: $features,
        );
    }

    /**
     * Get the difference in features and limits between two plans.
     *
     * @return array<string, mixed>
     */
    public function getPlanDifference(Plan $from, Plan $to): array
    {
        $isUpgrade = $to->sort_order > $from->sort_order;

        return [
            'is_upgrade' => $isUpgrade,
            'features' => $this->compareFeatures($from, $to),
            'limits' => $this->compareLimits($from, $to),
        ];
    }

    /**
     * Build feature matrix showing availability across all plans.
     *
     * @param  Collection<int, Plan>  $plans
     * @return array<string, array{label: string, category: string, availability: array<string, bool|string>}>
     */
    private function buildFeatureMatrix(Collection $plans): array
    {
        $matrix = [];

        foreach (Feature::cases() as $feature) {
            $availability = [];

            foreach ($plans as $plan) {
                $value = $plan->features[$feature->value] ?? false;
                $availability[$plan->slug] = $this->formatFeatureValue($value);
            }

            $matrix[$feature->value] = [
                'label' => $feature->getLabel(),
                'category' => $feature->getCategory(),
                'availability' => $availability,
            ];
        }

        return $matrix;
    }

    /**
     * Compare features between two plans.
     *
     * @return array{added: array<string, string>, removed: array<string, string>, changed: array<string, array{from: mixed, to: mixed}>}
     */
    private function compareFeatures(Plan $from, Plan $to): array
    {
        $added = [];
        $removed = [];
        $changed = [];

        foreach (Feature::cases() as $feature) {
            $fromValue = $from->features[$feature->value] ?? false;
            $toValue = $to->features[$feature->value] ?? false;

            $fromEnabled = $this->isFeatureEnabled($fromValue);
            $toEnabled = $this->isFeatureEnabled($toValue);

            if (! $fromEnabled && $toEnabled) {
                $added[$feature->value] = $feature->getLabel();

                continue;
            }

            if ($fromEnabled && ! $toEnabled) {
                $removed[$feature->value] = $feature->getLabel();

                continue;
            }

            // Both enabled but values differ (e.g., basic -> advanced)
            if ($fromEnabled && $toEnabled && $fromValue !== $toValue) {
                $changed[$feature->value] = [
                    'from' => $fromValue,
                    'to' => $toValue,
                ];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * Compare limits between two plans.
     *
     * @return array{improved: array<string, array{from: int|string, to: int|string}>, reduced: array<string, array{from: int|string, to: int|string}>}
     */
    private function compareLimits(Plan $from, Plan $to): array
    {
        $improved = [];
        $reduced = [];

        $allLimits = array_unique(array_merge(
            array_keys($from->limits ?? []),
            array_keys($to->limits ?? [])
        ));

        foreach ($allLimits as $limit) {
            $fromValue = $from->limits[$limit] ?? 0;
            $toValue = $to->limits[$limit] ?? 0;

            if ($fromValue === $toValue) {
                continue;
            }

            $comparison = [
                'from' => $fromValue === -1 ? 'unlimited' : $fromValue,
                'to' => $toValue === -1 ? 'unlimited' : $toValue,
            ];

            if ($this->isLimitImproved($fromValue, $toValue)) {
                $improved[$limit] = $comparison;

                continue;
            }

            $reduced[$limit] = $comparison;
        }

        return [
            'improved' => $improved,
            'reduced' => $reduced,
        ];
    }

    /**
     * Check if limit improved (higher or became unlimited).
     */
    private function isLimitImproved(int $from, int $to): bool
    {
        // To unlimited is always improvement
        if ($to === -1) {
            return true;
        }

        // From unlimited to limited is never improvement
        if ($from === -1) {
            return false;
        }

        return $to > $from;
    }

    /**
     * Format feature value for display.
     */
    private function formatFeatureValue(mixed $value): bool|string
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value === 'none' ? false : $value;
        }

        return (bool) $value;
    }

    /**
     * Check if a feature value indicates the feature is enabled.
     */
    private function isFeatureEnabled(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value !== 'none' && $value !== '';
        }

        return (bool) $value;
    }
}
