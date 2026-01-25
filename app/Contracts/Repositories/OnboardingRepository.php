<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Enums\BusinessType;
use App\Models\Tenant;

interface OnboardingRepository
{
    /**
     * @return array<string, mixed>
     */
    public function getTenantOnboardingStatus(Tenant $tenant): array;

    public function startOnboarding(Tenant $tenant, BusinessType $type): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveProgress(Tenant $tenant, string $step, array $data): void;

    public function completeOnboarding(Tenant $tenant): void;

    public function skipOnboarding(Tenant $tenant): void;

    public function resetOnboarding(Tenant $tenant): void;
}
