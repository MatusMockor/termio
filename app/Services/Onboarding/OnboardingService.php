<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Contracts\Repositories\OnboardingRepository;
use App\Contracts\Repositories\WorkingHoursRepository;
use App\DTOs\Onboarding\OnboardingStatusDTO;
use App\DTOs\WorkingHours\WorkingHoursDTO;
use App\Enums\BusinessType;
use App\Exceptions\InvalidOnboardingDataException;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class OnboardingService
{
    public function __construct(
        private readonly OnboardingRepository $repository,
        private readonly WorkingHoursRepository $workingHoursRepository,
    ) {}

    /**
     * Start the onboarding process for a tenant.
     */
    public function startOnboarding(Tenant $tenant, BusinessType $type): void
    {
        DB::transaction(function () use ($tenant, $type): void {
            $this->repository->startOnboarding($tenant, $type);
        });
    }

    /**
     * Complete the onboarding process.
     */
    public function completeOnboarding(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $this->syncBusinessWorkingHoursFromProgress($tenant);
            $this->repository->completeOnboarding($tenant);
        });
    }

    /**
     * Get the current onboarding status for a tenant.
     */
    public function getOnboardingStatus(Tenant $tenant): OnboardingStatusDTO
    {
        $data = $this->repository->getTenantOnboardingStatus($tenant);

        return OnboardingStatusDTO::fromArray($data);
    }

    /**
     * Save progress for current onboarding step.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveOnboardingProgress(Tenant $tenant, string $step, array $data): void
    {
        DB::transaction(function () use ($tenant, $step, $data): void {
            $this->repository->saveProgress($tenant, $step, $data);
        });
    }

    /**
     * Skip onboarding and mark as complete.
     */
    public function skipOnboarding(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $this->repository->skipOnboarding($tenant);
        });
    }

    /**
     * Determine if tenant needs onboarding.
     */
    public function needsOnboarding(Tenant $tenant): bool
    {
        return ! $tenant->isOnboardingCompleted();
    }

    /**
     * Reset onboarding progress (for re-onboarding).
     */
    public function resetOnboarding(Tenant $tenant): void
    {
        $this->repository->resetOnboarding($tenant);
    }

    private function syncBusinessWorkingHoursFromProgress(Tenant $tenant): void
    {
        $workingHours = $this->extractWorkingHoursFromProgress($tenant);

        if (! $workingHours) {
            return;
        }

        $this->workingHoursRepository->deleteByTenantAndStaff($tenant->id, null);

        foreach ($workingHours as $hours) {
            $this->workingHoursRepository->create(new WorkingHoursDTO(
                tenantId: $tenant->id,
                staffId: null,
                dayOfWeek: $hours['day_of_week'],
                startTime: $hours['start_time'],
                endTime: $hours['end_time'],
                activeFlag: ($hours['is_active'] ?? true) ? 1 : 0,
            ));
        }
    }

    /**
     * @return array<int, array{day_of_week: int, start_time: string, end_time: string, is_active?: bool}>|null
     */
    private function extractWorkingHoursFromProgress(Tenant $tenant): ?array
    {
        $onboardingData = $tenant->onboarding_data ?? [];
        $hasWorkingHoursStep = array_key_exists('working_hours', $onboardingData);
        $workingHoursStep = $onboardingData['working_hours'] ?? null;

        if (! is_array($workingHoursStep)) {
            if (! $hasWorkingHoursStep) {
                return null;
            }

            throw InvalidOnboardingDataException::forTenantWorkingHours($tenant->id);
        }

        $hasNestedWorkingHours = array_key_exists('working_hours', $workingHoursStep);
        $workingHoursPayload = $workingHoursStep['working_hours'] ?? $workingHoursStep;

        if (! is_array($workingHoursPayload)) {
            if (! $hasNestedWorkingHours) {
                return null;
            }

            throw InvalidOnboardingDataException::forTenantWorkingHours($tenant->id);
        }

        $validator = Validator::make(
            ['working_hours' => $workingHoursPayload],
            [
                'working_hours' => ['array'],
                'working_hours.*.day_of_week' => ['required', 'integer', 'distinct', 'min:0', 'max:6'],
                'working_hours.*.start_time' => ['required', 'date_format:H:i'],
                'working_hours.*.end_time' => ['required', 'date_format:H:i', 'after:working_hours.*.start_time'],
                'working_hours.*.is_active' => ['sometimes', 'boolean'],
            ],
        );

        if ($validator->fails()) {
            throw InvalidOnboardingDataException::forTenantWorkingHours(
                $tenant->id,
                $validator->errors()->toArray(),
            );
        }

        $validated = $validator->validated();
        $workingHours = $validated['working_hours'] ?? null;

        if (! is_array($workingHours)) {
            return null;
        }

        return $workingHours;
    }
}
