<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\DTOs\Onboarding\SaveProgressDTO;
use App\Enums\BusinessType;
use App\Rules\EndTimeAfterStartTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveOnboardingProgressRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dayOfWeekMin = (int) config('working_hours.day_of_week.min');
        $dayOfWeekMax = (int) config('working_hours.day_of_week.max');
        $leadTimeMin = (int) config('reservation.limits.lead_time_hours.min');
        $leadTimeMax = (int) config('reservation.limits.lead_time_hours.max');
        $maxDaysMin = (int) config('reservation.limits.max_days_in_advance.min');
        $maxDaysMax = (int) config('reservation.limits.max_days_in_advance.max');
        $slotIntervalMin = (int) config('reservation.limits.slot_interval_minutes.min');
        $slotIntervalMax = (int) config('reservation.limits.slot_interval_minutes.max');
        $slotIntervalMultipleOf = (int) config('reservation.limits.slot_interval_minutes.multiple_of');

        return [
            'step' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
            'data.business_type' => ['sometimes', Rule::enum(BusinessType::class)],
            'data.booking_slug' => ['sometimes', 'string', 'max:255'],
            'data.phone' => ['sometimes', 'string', 'max:20'],
            'data.address' => ['sometimes', 'string', 'max:255'],
            'data.city' => ['sometimes', 'string', 'max:100'],
            'data.postal_code' => ['sometimes', 'string', 'max:20'],
            'data.country' => ['sometimes', 'string', 'max:100'],
            'data.services' => ['sometimes', 'array'],
            'data.services.*.name' => ['required_with:data.services', 'string', 'max:255'],
            'data.services.*.duration' => ['required_with:data.services', 'integer', 'min:5'],
            'data.services.*.price' => ['required_with:data.services', 'numeric', 'min:0'],
            'data.staff_members' => ['sometimes', 'array'],
            'data.staff_members.*.first_name' => ['required_with:data.staff_members', 'string', 'max:100'],
            'data.staff_members.*.last_name' => ['required_with:data.staff_members', 'string', 'max:100'],
            'data.staff_members.*.email' => ['required_with:data.staff_members', 'email', 'max:255'],
            'data.working_hours' => ['sometimes', 'array'],
            'data.working_hours.*.day_of_week' => [
                'required_with:data.working_hours',
                'integer',
                'distinct',
                'min:'.$dayOfWeekMin,
                'max:'.$dayOfWeekMax,
            ],
            'data.working_hours.*.start_time' => ['required_with:data.working_hours', 'date_format:H:i'],
            'data.working_hours.*.end_time' => ['required_with:data.working_hours', 'date_format:H:i', new EndTimeAfterStartTime],
            'data.working_hours.*.is_active' => ['sometimes', 'boolean'],
            'data.reservation_settings' => ['sometimes', 'array'],
            'data.reservation_settings.lead_time_hours' => [
                'required_with:data.reservation_settings',
                'integer',
                'min:'.$leadTimeMin,
                'max:'.$leadTimeMax,
            ],
            'data.reservation_settings.max_days_in_advance' => [
                'required_with:data.reservation_settings',
                'integer',
                'min:'.$maxDaysMin,
                'max:'.$maxDaysMax,
            ],
            'data.reservation_settings.slot_interval_minutes' => [
                'required_with:data.reservation_settings',
                'integer',
                'min:'.$slotIntervalMin,
                'max:'.$slotIntervalMax,
                'multiple_of:'.$slotIntervalMultipleOf,
            ],
        ];
    }

    public function getStep(): string
    {
        return $this->validated('step');
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->validated('data') ?? [];
    }

    public function toDTO(): SaveProgressDTO
    {
        return new SaveProgressDTO(
            step: $this->getStep(),
            data: $this->getData(),
        );
    }
}
