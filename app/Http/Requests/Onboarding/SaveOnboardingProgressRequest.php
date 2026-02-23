<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\DTOs\Onboarding\SaveProgressDTO;
use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveOnboardingProgressRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
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
            'data.working_hours.*.day_of_week' => ['required_with:data.working_hours', 'integer', 'min:0', 'max:6'],
            'data.working_hours.*.start_time' => ['required_with:data.working_hours', 'date_format:H:i'],
            'data.working_hours.*.end_time' => ['required_with:data.working_hours', 'date_format:H:i', 'after:data.working_hours.*.start_time'],
            'data.working_hours.*.is_active' => ['boolean'],
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
