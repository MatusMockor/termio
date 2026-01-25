<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BusinessType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\SaveOnboardingProgressRequest;
use App\Http\Requests\Onboarding\StartOnboardingRequest;
use App\Http\Resources\Onboarding\OnboardingStatusResource;
use App\Http\Resources\Onboarding\ServiceTemplateResource;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\ServiceTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
        private readonly ServiceTemplateService $serviceTemplateService,
    ) {}

    /**
     * Get current onboarding status.
     */
    public function status(): OnboardingStatusResource
    {
        $tenant = auth()->user()->tenant;
        $status = $this->onboardingService->getOnboardingStatus($tenant);

        return new OnboardingStatusResource($status);
    }

    /**
     * Get service templates for a business type.
     */
    public function templates(string $businessType): AnonymousResourceCollection
    {
        if (! in_array($businessType, ['hair_beauty', 'spa_wellness', 'other'])) {
            abort(404, 'Invalid business type');
        }

        $type = BusinessType::from($businessType);
        $templates = $this->serviceTemplateService->getTemplatesForBusinessType($type);

        return ServiceTemplateResource::collection($templates);
    }

    /**
     * Start the onboarding process.
     */
    public function start(StartOnboardingRequest $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $businessType = $request->getBusinessType();

        $this->onboardingService->startOnboarding($tenant, $businessType);

        return response()->json([
            'message' => 'Onboarding started successfully',
            'business_type' => $businessType->value,
        ]);
    }

    /**
     * Save progress for current step.
     */
    public function saveProgress(SaveOnboardingProgressRequest $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $step = $request->getStep();
        $data = $request->getData();

        $this->onboardingService->saveOnboardingProgress($tenant, $step, $data);

        return response()->json([
            'message' => 'Progress saved successfully',
            'step' => $step,
        ]);
    }

    /**
     * Complete the onboarding process.
     */
    public function complete(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        $this->onboardingService->completeOnboarding($tenant);

        return response()->json([
            'message' => 'Onboarding completed successfully',
        ]);
    }

    /**
     * Skip the onboarding process.
     */
    public function skip(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        $this->onboardingService->skipOnboarding($tenant);

        return response()->json([
            'message' => 'Onboarding skipped successfully',
        ]);
    }
}
