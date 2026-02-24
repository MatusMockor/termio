<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Tenant;

interface OnboardingProgressValidationServiceContract
{
    /**
     * @return array<int, array{day_of_week: int, start_time: string, end_time: string, is_active?: bool}>|null
     */
    public function extractWorkingHours(Tenant $tenant): ?array;

    /**
     * @return array{lead_time_hours: int, max_days_in_advance: int, slot_interval_minutes: int}|null
     */
    public function extractReservationSettings(Tenant $tenant): ?array;
}
