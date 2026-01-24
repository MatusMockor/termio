<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Subscription\FeatureListService;
use App\Services\Tenant\TenantContextService;
use Illuminate\Http\JsonResponse;

final class SubscriptionFeatureController extends Controller
{
    public function __construct(
        private readonly FeatureListService $featureListService,
        private readonly TenantContextService $tenantContext,
    ) {}

    /**
     * Get all features with availability status for current tenant.
     */
    public function index(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $features = $this->featureListService->getFeatureStatus($tenant);

        return response()->json([
            'data' => $features,
        ]);
    }

    /**
     * Check if a specific feature is available.
     */
    public function show(string $feature): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $featureStatus = $this->featureListService->getSingleFeatureStatus($tenant, $feature);

        if (! $featureStatus) {
            return response()->json([
                'error' => 'unknown_feature',
                'message' => "Feature '{$feature}' is not recognized.",
            ], 400);
        }

        return response()->json([
            'data' => $featureStatus,
        ]);
    }

    /**
     * Get all features grouped by category.
     */
    public function grouped(): JsonResponse
    {
        $grouped = $this->featureListService->getFeaturesGrouped();

        return response()->json([
            'data' => $grouped,
        ]);
    }
}
