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
use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\UsageLimitServiceContract;
use App\Enums\SubscriptionStatus;
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
        private readonly DefaultPaymentMethodGuardContract $paymentMethodGuard,
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
            return $this->subscriptionExceptionResponse($e);
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
                'plan' => new PlanResource($currentPlan),
                'is_on_trial' => false,
                'trial_days_remaining' => 0,
                'needs_payment_method' => false,
                'pending_change' => null,
            ]);
        }

        $subscription->load(['plan', 'scheduledPlan']);

        return response()->json([
            'data' => new SubscriptionResource($subscription),
            'plan' => new PlanResource($subscription->plan),
            'is_on_trial' => $this->subscriptionService->isOnTrial($tenant),
            'trial_days_remaining' => $this->subscriptionService->getTrialDaysRemaining($tenant),
            'needs_payment_method' => $this->needsPaymentMethod($subscription),
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
            $billingNotice = $this->buildBillingNotice($subscription);

            return response()->json([
                'data' => new SubscriptionResource($subscription->load('plan')),
                'message' => $this->buildUpgradeMessage($billingNotice['will_charge_after_trial']),
                'billing_notice' => $billingNotice,
            ]);
        } catch (SubscriptionException $e) {
            return $this->subscriptionExceptionResponse($e);
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
            return $this->subscriptionExceptionResponse($e);
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
            return $this->subscriptionExceptionResponse($e);
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
            return $this->subscriptionExceptionResponse($e);
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
            return $this->subscriptionExceptionResponse($e);
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

    private function buildUpgradeMessage(bool $willChargeAfterTrial): string
    {
        if ($willChargeAfterTrial) {
            return 'Subscription upgraded successfully. Billing starts after trial ends.';
        }

        return 'Subscription upgraded successfully.';
    }

    /**
     * @return array{
     *     will_charge_after_trial: bool,
     *     trial_ends_at: string|null,
     *     charge_starts_at: string|null
     * }
     */
    private function buildBillingNotice(\App\Models\Subscription $subscription): array
    {
        $trialEndsAt = $subscription->trial_ends_at?->toIso8601String();
        $willChargeAfterTrial = $this->shouldChargeAfterTrial($subscription);

        return [
            'will_charge_after_trial' => $willChargeAfterTrial,
            'trial_ends_at' => $trialEndsAt,
            'charge_starts_at' => $willChargeAfterTrial ? $trialEndsAt : null,
        ];
    }

    private function shouldChargeAfterTrial(\App\Models\Subscription $subscription): bool
    {
        return $subscription->stripe_status === SubscriptionStatus::Trialing
            && $subscription->trial_ends_at !== null;
    }

    private function needsPaymentMethod(\App\Models\Subscription $subscription): bool
    {
        if (str_starts_with($subscription->stripe_id, 'free_')) {
            return false;
        }

        return ! $this->paymentMethodGuard->hasLiveDefaultPaymentMethod($subscription->tenant);
    }

    private function subscriptionExceptionResponse(SubscriptionException $exception): JsonResponse
    {
        $response = [
            'error' => $exception->getMessage(),
        ];

        $errorCode = $exception->getErrorCode();
        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        $action = $exception->getAction();
        if ($action !== null) {
            $response['action'] = $action;
        }

        $violations = $exception->getViolations();
        if ($violations) {
            $response['violations'] = $violations;
        }

        return response()->json($response, 400);
    }
}
