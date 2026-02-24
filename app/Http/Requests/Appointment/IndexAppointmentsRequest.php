<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointment;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

final class IndexAppointmentsRequest extends FormRequest
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
        $maximum = (int) config('pagination.appointments.max');

        return [
            'date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'staff_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'status' => ['nullable', 'in:pending,confirmed,in_progress,completed,cancelled,no_show'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', "max:{$maximum}"],
        ];
    }

    public function getDate(): ?Carbon
    {
        $date = $this->validated('date');

        return $date ? Carbon::parse($date) : null;
    }

    public function getStartDate(): ?Carbon
    {
        $date = $this->validated('start_date');

        return $date ? Carbon::parse($date) : null;
    }

    public function getEndDate(): ?Carbon
    {
        $date = $this->validated('end_date');

        return $date ? Carbon::parse($date) : null;
    }

    public function getStaffId(): ?int
    {
        $staffId = $this->validated('staff_id');

        return $staffId ? (int) $staffId : null;
    }

    public function getStatus(): ?string
    {
        return $this->validated('status');
    }

    public function getPerPage(): int
    {
        $value = $this->validated('per_page');

        if (! $value) {
            return (int) config('pagination.appointments.default');
        }

        return (int) $value;
    }
}
