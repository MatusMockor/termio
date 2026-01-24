<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Feature;
use Exception;
use Illuminate\Http\JsonResponse;

final class FeatureNotAvailableException extends Exception
{
    private readonly string $featureKey;

    private readonly string $requiredPlan;

    public function __construct(string $feature, ?string $requiredPlan = null)
    {
        $this->featureKey = $feature;
        $this->requiredPlan = $requiredPlan ?? $this->resolveRequiredPlan($feature);

        parent::__construct(
            "This feature requires {$this->getRequiredPlanDisplay()} plan or higher."
        );
    }

    public static function forFeature(Feature $feature): self
    {
        return new self($feature->value, $feature->getMinimumPlan());
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'feature_not_available',
            'message' => $this->getMessage(),
            'feature' => $this->featureKey,
            'required_plan' => $this->requiredPlan,
            'upgrade_url' => '/billing/upgrade',
        ], 403);
    }

    public function getFeatureKey(): string
    {
        return $this->featureKey;
    }

    public function getRequiredPlan(): string
    {
        return $this->requiredPlan;
    }

    private function resolveRequiredPlan(string $feature): string
    {
        $featureEnum = Feature::tryFromString($feature);

        if ($featureEnum) {
            return $featureEnum->getMinimumPlan();
        }

        // Default to premium for unknown features
        return 'premium';
    }

    private function getRequiredPlanDisplay(): string
    {
        return strtoupper($this->requiredPlan);
    }
}
