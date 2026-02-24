<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Contracts\Services\OnboardingProgressValidationServiceContract;
use App\Exceptions\InvalidOnboardingDataException;
use App\Models\Tenant;
use App\Rules\EndTimeAfterStartTime;
use Illuminate\Support\Facades\Validator;

final class OnboardingProgressValidationService implements OnboardingProgressValidationServiceContract
{
    /**
     * @return array<int, array{day_of_week: int, start_time: string, end_time: string, is_active?: bool}>|null
     */
    public function extractWorkingHours(Tenant $tenant): ?array
    {
        $workingHoursPayload = $this->resolveStepPayload($tenant, 'working_hours');

        if (! $workingHoursPayload) {
            return null;
        }

        $dayOfWeekMin = (int) config('working_hours.day_of_week.min');
        $dayOfWeekMax = (int) config('working_hours.day_of_week.max');

        $validator = Validator::make(
            ['working_hours' => $workingHoursPayload],
            [
                'working_hours' => ['array'],
                'working_hours.*.day_of_week' => ['required', 'integer', 'distinct', 'min:'.$dayOfWeekMin, 'max:'.$dayOfWeekMax],
                'working_hours.*.start_time' => ['required', 'date_format:H:i'],
                'working_hours.*.end_time' => ['required', 'date_format:H:i', new EndTimeAfterStartTime],
                'working_hours.*.is_active' => ['sometimes', 'boolean'],
            ],
        );

        if ($validator->fails()) {
            $this->throwInvalidStepPayload('working_hours', $tenant->id, $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $workingHours = $validated['working_hours'] ?? null;

        if (! is_array($workingHours)) {
            $this->throwInvalidStepPayload('working_hours', $tenant->id);
        }

        return $workingHours;
    }

    /**
     * @return array{lead_time_hours: int, max_days_in_advance: int, slot_interval_minutes: int}|null
     */
    public function extractReservationSettings(Tenant $tenant): ?array
    {
        $reservationSettingsPayload = $this->resolveStepPayload($tenant, 'reservation_settings');

        if (! $reservationSettingsPayload) {
            return null;
        }

        $leadTimeMin = (int) config('reservation.limits.lead_time_hours.min');
        $leadTimeMax = (int) config('reservation.limits.lead_time_hours.max');
        $maxDaysMin = (int) config('reservation.limits.max_days_in_advance.min');
        $maxDaysMax = (int) config('reservation.limits.max_days_in_advance.max');
        $slotIntervalMin = (int) config('reservation.limits.slot_interval_minutes.min');
        $slotIntervalMax = (int) config('reservation.limits.slot_interval_minutes.max');
        $slotIntervalMultipleOf = (int) config('reservation.limits.slot_interval_minutes.multiple_of');

        $validator = Validator::make(
            ['reservation_settings' => $reservationSettingsPayload],
            [
                'reservation_settings' => ['array'],
                'reservation_settings.lead_time_hours' => ['required', 'integer', 'min:'.$leadTimeMin, 'max:'.$leadTimeMax],
                'reservation_settings.max_days_in_advance' => ['required', 'integer', 'min:'.$maxDaysMin, 'max:'.$maxDaysMax],
                'reservation_settings.slot_interval_minutes' => [
                    'required',
                    'integer',
                    'min:'.$slotIntervalMin,
                    'max:'.$slotIntervalMax,
                    'multiple_of:'.$slotIntervalMultipleOf,
                ],
            ],
        );

        if ($validator->fails()) {
            $this->throwInvalidStepPayload('reservation_settings', $tenant->id, $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $reservationSettings = $validated['reservation_settings'] ?? null;

        if (! is_array($reservationSettings)) {
            $this->throwInvalidStepPayload('reservation_settings', $tenant->id);
        }

        return [
            'lead_time_hours' => (int) $reservationSettings['lead_time_hours'],
            'max_days_in_advance' => (int) $reservationSettings['max_days_in_advance'],
            'slot_interval_minutes' => (int) $reservationSettings['slot_interval_minutes'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStepPayload(Tenant $tenant, string $stepKey): ?array
    {
        $onboardingData = $tenant->onboarding_data ?? [];
        $hasStep = array_key_exists($stepKey, $onboardingData);
        $stepData = $onboardingData[$stepKey] ?? null;

        if (! is_array($stepData)) {
            if (! $hasStep) {
                return null;
            }

            $this->throwInvalidStepPayload($stepKey, $tenant->id);
        }

        $hasNestedStep = array_key_exists($stepKey, $stepData);
        $payload = $stepData[$stepKey] ?? $stepData;

        if (! is_array($payload)) {
            if (! $hasNestedStep) {
                return null;
            }

            $this->throwInvalidStepPayload($stepKey, $tenant->id);
        }

        if (! $payload) {
            return null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function throwInvalidStepPayload(string $stepKey, int $tenantId, array $errors = []): never
    {
        if ($stepKey === 'working_hours') {
            throw InvalidOnboardingDataException::forTenantWorkingHours($tenantId, $errors);
        }

        throw InvalidOnboardingDataException::forTenantReservationSettings($tenantId, $errors);
    }
}
