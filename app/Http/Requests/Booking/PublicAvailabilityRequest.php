<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

final class PublicAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'staff_id' => ['nullable', 'integer'],
        ];
    }

    public function getServiceId(): int
    {
        return (int) $this->validated('service_id');
    }

    public function getDate(): string
    {
        return $this->validated('date');
    }

    public function getStaffId(): ?int
    {
        $staffId = $this->validated('staff_id');

        return $staffId !== null ? (int) $staffId : null;
    }
}
