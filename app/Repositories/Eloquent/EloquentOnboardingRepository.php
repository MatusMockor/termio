<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\OnboardingRepository;
use App\Enums\BusinessType;
use App\Models\Tenant;

final class EloquentOnboardingRepository implements OnboardingRepository
{
    public function getTenantOnboardingStatus(Tenant $tenant): array
    {
        return [
            'completed' => $tenant->isOnboardingCompleted(),
            'business_type' => $tenant->business_type?->value,
            'current_step' => $tenant->onboarding_step,
            'data' => $tenant->onboarding_data ?? [],
            'completed_at' => $tenant->onboarding_completed_at?->toIso8601String(),
        ];
    }

    public function startOnboarding(Tenant $tenant, BusinessType $type): void
    {
        $tenant->business_type = $type;
        $tenant->onboarding_step = 'business_details';
        $tenant->onboarding_data = [
            'started_at' => now()->toIso8601String(),
        ];
        $tenant->save();
    }

    public function saveProgress(Tenant $tenant, string $step, array $data): void
    {
        $tenant->onboarding_step = $step;

        $existingData = $tenant->onboarding_data ?? [];
        $tenant->onboarding_data = array_merge($existingData, [
            $step => $data,
            'last_updated_at' => now()->toIso8601String(),
        ]);

        $tenant->save();
    }

    public function completeOnboarding(Tenant $tenant): void
    {
        $tenant->markOnboardingComplete();
    }

    public function skipOnboarding(Tenant $tenant): void
    {
        if (! $tenant->business_type) {
            $tenant->business_type = BusinessType::Other;
        }

        $tenant->markOnboardingComplete();
    }

    public function resetOnboarding(Tenant $tenant): void
    {
        $tenant->onboarding_completed_at = null;
        $tenant->onboarding_step = null;
        $tenant->onboarding_data = null;
        $tenant->save();
    }
}
