<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Subscription\SubscriptionCancelAction;
use App\Actions\Subscription\SubscriptionCreateAction;
use App\Actions\Subscription\SubscriptionDowngradeAction;
use App\Actions\Subscription\SubscriptionImmediateUpgradeAction;
use App\Actions\Subscription\SubscriptionResumeAction;
use App\Actions\Subscription\SubscriptionUpgradeAction;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\UsageLimitServiceContract;
use App\Enums\UsageResource;
use App\Exceptions\SubscriptionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\CancelSubscriptionRequest;
use App\Http\Requests\Subscription\CreateSubscriptionRequest;
use App\Http\Requests\Subscription\DowngradeSubscriptionRequest;
use App\Http\Requests\Subscription\ImmediateUpgradeSubscriptionRequest;
use App\Http\Requests\Subscription\UpgradeSubscriptionRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Services\Tenant\TenantContextService;
use Illuminate\Http\JsonResponse;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly UsageLimitServiceContract $usageLimitService,
        private readonly TenantContextService $tenantContext,
    ) {}

    /**
     * Create a new subscription.
     */
    public function store(
        CreateSubscriptionRequest $request,
        SubscriptionCreateAction $action
    ): JsonResponse {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        try {
            $subscription = $action->handle($request->toDTO(), $tenant);

            return response()->json([
                'data' => new SubscriptionResource($subscription->load('plan')),
                'message' => 'Subscription created successfully.',
            ], 201);
        } catch (SubscriptionException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get current subscription details.
     */
    public function show(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (! $subscription) {
            $currentPlan = $this->subscriptionService->getCurrentPlan($tenant);

            return response()->json([
                'data' => null,
                'plan' => $currentPlan ? new PlanResource($currentPlan) : null,
                'is_on_trial' => false,
                'trial_days_remaining' => 0,
                'pending_change' => null,
            ]);
        }

        return response()->json([
            'data' => new SubscriptionResource($subscription->load('plan')),
            'plan' => new PlanResource($subscription->plan),
            'is_on_trial' => $this->subscriptionService->isOnTrial($tenant),
            'trial_days_remaining' => $this->subscriptionService->getTrialDaysRemaining($tenant),
            'pending_change' => $this->subscriptionService->getPendingChange($tenant),
        ]);
    }

    /**
     * Upgrade subscription to a higher plan.
     */
    public function upgrade(
        UpgradeSubscriptionRequest $request,
        SubscriptionUpgradeAction $action
    ): JsonResponse {
        try {
            $subscription = $action->handle($request->toDTO());

            return response()->json([
                'data' => new SubscriptionResource($subscription->load('plan')),
                'message' => 'Subscription upgraded successfully.',
            ]);
        } catch (SubscriptionException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Upgrade subscription immediately.
     */
    public function upgradeImmediate(
        ImmediateUpgradeSubscriptionRequest $request,
        SubscriptionImmediateUpgradeAction $action
    ): JsonResponse {
        try {
            $subscription = $action->handle($request->toDTO());

            return response()->json([
                'data' => new SubscriptionResource($subscription->load('plan')),
                'message' => 'Subscription upgraded immediately.',
            ]);
        } catch (SubscriptionException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Schedule subscription downgrade to a lower plan.
     */
    public function downgrade(
        DowngradeSubscriptionRequest $request,
        SubscriptionDowngradeAction $action
    ): JsonResponse {
        try {
            $subscription = $action->handle($request->toDTO());

            return response()->json([
                'data' => new SubscriptionResource($subscription->load(['plan', 'scheduledPlan'])),
                'message' => 'Downgrade scheduled successfully.',
            ]);
        } catch (SubscriptionException $e) {
            $response = ['error' => $e->getMessage()];

            if (! empty($e->getViolations())) {
                $response['violations'] = $e->getViolations();
            }

            return response()->json($response, 400);
        }
    }

    /**
     * Cancel subscription at period end.
     */
    public function cancel(
        CancelSubscriptionRequest $request,
        SubscriptionCancelAction $action
    ): JsonResponse {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (! $subscription) {
            return response()->json(['error' => 'No active subscription found.'], 404);
        }

        try {
            $subscription = $action->handle($subscription->id, $request->getReason());

            return response()->json([
                'data' => new SubscriptionResource($subscription->load('plan')),
                'message' => 'Subscription cancellation scheduled.',
            ]);
        } catch (SubscriptionException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(SubscriptionResumeAction $action): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (! $subscription) {
            return response()->json(['error' => 'No active subscription found.'], 404);
        }

        try {
            $subscription = $action->handle($subscription->id);

            return response()->json([
                'data' => new SubscriptionResource($subscription->load('plan')),
                'message' => 'Subscription resumed successfully.',
            ]);
        } catch (SubscriptionException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get current usage statistics.
     */
    public function usage(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $usageStats = $this->usageLimitService->getUsageStats($tenant);

        return response()->json([
            'data' => $usageStats,
            'warnings' => [
                UsageResource::Reservations->value => $this->usageLimitService->isNearLimit($tenant, UsageResource::Reservations),
                UsageResource::Users->value => $this->usageLimitService->isNearLimit($tenant, UsageResource::Users),
                UsageResource::Services->value => $this->usageLimitService->isNearLimit($tenant, UsageResource::Services),
            ],
        ]);
    }
}
