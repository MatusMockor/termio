<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

final class PublicAvailableDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2024|max:2030',
            'staff_id' => 'nullable|integer',
        ];
    }

    public function getServiceId(): int
    {
        return (int) $this->validated('service_id');
    }

    public function getMonth(): int
    {
        return (int) $this->validated('month');
    }

    public function getYear(): int
    {
        return (int) $this->validated('year');
    }

    public function getStaffId(): ?int
    {
        $staffId = $this->validated('staff_id');

        return $staffId ? (int) $staffId : null;
    }
}
