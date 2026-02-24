<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointment;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

final class CalendarAppointmentsRequest extends FormRequest
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
        $maxPerDay = (int) config('appointments.calendar.per_day.max');

        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'staff_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'status' => ['nullable', 'in:pending,confirmed,in_progress,completed,cancelled,no_show'],
            'per_day' => ['nullable', 'integer', 'min:1', "max:{$maxPerDay}"],
        ];
    }

    public function getStartDate(): Carbon
    {
        return Carbon::parse($this->validated('start_date'));
    }

    public function getEndDate(): Carbon
    {
        return Carbon::parse($this->validated('end_date'));
    }

    public function getStaffId(): ?int
    {
        $staffId = $this->validated('staff_id');

        if (! $staffId) {
            return null;
        }

        return (int) $staffId;
    }

    public function getStatus(): ?string
    {
        return $this->validated('status');
    }

    public function getPerDay(): int
    {
        $perDay = $this->validated('per_day');

        if (! $perDay) {
            return (int) config('appointments.calendar.per_day.default');
        }

        return (int) $perDay;
    }
}
