<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Contracts\Repositories\OnboardingRepository;
use App\DTOs\Onboarding\OnboardingStatusDTO;
use App\Enums\BusinessType;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class OnboardingService
{
    public function __construct(
        private readonly OnboardingRepository $repository,
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
}
