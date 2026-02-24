<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointment;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

final class CalendarDayAppointmentsRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'staff_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'status' => ['nullable', 'in:pending,confirmed,in_progress,completed,cancelled,no_show'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', "max:{$maxPerDay}"],
        ];
    }

    public function getDate(): Carbon
    {
        return Carbon::parse($this->validated('date'));
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

    public function getOffset(): int
    {
        return (int) ($this->validated('offset') ?? 0);
    }

    public function getLimit(): int
    {
        $limit = $this->validated('limit');

        if (! $limit) {
            return (int) config('appointments.calendar.per_day.default');
        }

        return (int) $limit;
    }
}
