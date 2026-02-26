<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\PlanRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Services\Subscription\PlanComparisonService;
use Illuminate\Http\JsonResponse;

final class PlanController extends Controller
{
    public function __construct(
        private readonly PlanRepository $plans,
        private readonly PlanComparisonService $comparisonService,
    ) {}

    /**
     * List all public active plans.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PlanResource::collection($this->plans->getPublic()),
        ]);
    }

    /**
     * Get plan details by slug.
     */
    public function show(Plan $plan): JsonResponse
    {
        if (! $plan->is_active || ! $plan->is_public) {
            return response()->json([
                'error' => 'plan_not_found',
                'message' => 'Plan not found.',
            ], 404);
        }

        return response()->json([
            'data' => new PlanResource($plan),
        ]);
    }

    /**
     * Get plan comparison matrix with feature availability.
     */
    public function compare(): JsonResponse
    {
        $matrix = $this->comparisonService->getComparisonMatrix();

        return response()->json([
            'data' => [
                'plans' => PlanResource::collection($matrix['plans']),
                'features' => $matrix['features'],
            ],
        ]);
    }
}
