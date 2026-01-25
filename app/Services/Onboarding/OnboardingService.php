<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Enums\BusinessType;
use App\Models\Tenant;

final class OnboardingService
{
    /**
     * Start the onboarding process for a tenant.
     */
    public function startOnboarding(Tenant $tenant, BusinessType $type): void
    {
        $tenant->business_type = $type;
        $tenant->onboarding_step = 'business_details';
        $tenant->onboarding_data = [
            'started_at' => now()->toIso8601String(),
        ];
        $tenant->save();
    }

    /**
     * Complete the onboarding process.
     */
    public function completeOnboarding(Tenant $tenant): void
    {
        $tenant->markOnboardingComplete();
    }

    /**
     * Get the current onboarding status for a tenant.
     *
     * @return array<string, mixed>
     */
    public function getOnboardingStatus(Tenant $tenant): array
    {
        return [
            'completed' => $tenant->isOnboardingCompleted(),
            'business_type' => $tenant->business_type?->value,
            'current_step' => $tenant->onboarding_step,
            'data' => $tenant->onboarding_data ?? [],
            'completed_at' => $tenant->onboarding_completed_at?->toIso8601String(),
        ];
    }

    /**
     * Save progress for current onboarding step.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveOnboardingProgress(Tenant $tenant, string $step, array $data): void
    {
        $tenant->onboarding_step = $step;

        $existingData = $tenant->onboarding_data ?? [];
        $tenant->onboarding_data = array_merge($existingData, [
            $step => $data,
            'last_updated_at' => now()->toIso8601String(),
        ]);

        $tenant->save();
    }

    /**
     * Skip onboarding and mark as complete.
     */
    public function skipOnboarding(Tenant $tenant): void
    {
        if (! $tenant->business_type) {
            $tenant->business_type = BusinessType::Other;
        }

        $tenant->markOnboardingComplete();
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
        $tenant->onboarding_completed_at = null;
        $tenant->onboarding_step = null;
        $tenant->onboarding_data = null;
        $tenant->save();
    }
}
