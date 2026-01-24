<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Plan\PlanCreateAction;
use App\Actions\Admin\Plan\PlanDeactivateAction;
use App\Actions\Admin\Plan\PlanUpdateAction;
use App\Contracts\Repositories\PlanRepository;
use App\Exceptions\PlanHasActiveSubscribersException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\Admin\PlanResource;
use App\Models\Plan;
use App\Services\Admin\PlanStatisticsService;
use Illuminate\Http\JsonResponse;

final class PlanController extends Controller
{
    public function __construct(
        private readonly PlanRepository $planRepository,
    ) {}

    public function index(): JsonResponse
    {
        $plans = $this->planRepository->getAll();

        $plansWithCount = $plans->map(function (Plan $plan): Plan {
            $plan->subscriber_count = $this->planRepository->getSubscriberCount($plan);

            return $plan;
        });

        return response()->json([
            'data' => PlanResource::collection($plansWithCount),
        ]);
    }

    public function store(StorePlanRequest $request, PlanCreateAction $action): JsonResponse
    {
        $plan = $action->handle($request->toDTO());

        return (new PlanResource($plan))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Plan $plan): JsonResponse
    {
        $plan->subscriber_count = $this->planRepository->getSubscriberCount($plan);

        return response()->json([
            'data' => new PlanResource($plan),
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan, PlanUpdateAction $action): JsonResponse
    {
        $updatedPlan = $action->handle($plan, $request->toDTO());
        $updatedPlan->subscriber_count = $this->planRepository->getSubscriberCount($updatedPlan);

        return response()->json([
            'data' => new PlanResource($updatedPlan),
        ]);
    }

    public function destroy(Plan $plan, PlanDeactivateAction $action): JsonResponse
    {
        try {
            $action->handle($plan);

            return response()->json([
                'message' => 'Plan deactivated successfully.',
            ]);
        } catch (PlanHasActiveSubscribersException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function statistics(PlanStatisticsService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->getStatistics(),
        ]);
    }
}
