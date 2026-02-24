<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointment;

use App\DTOs\Appointment\GetCalendarDayAppointmentsDTO;
use App\Enums\AppointmentStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CalendarDayAppointmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxPerDay = (int) config('appointments.calendar.per_day.max');

        return [
            'date' => ['required', 'date'],
            'staff_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'status' => ['nullable', Rule::in(AppointmentStatus::values())],
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

    public function getStatus(): ?AppointmentStatus
    {
        $status = $this->validated('status');

        if (! is_string($status)) {
            return null;
        }

        return AppointmentStatus::tryFrom($status);
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

    public function toDTO(): GetCalendarDayAppointmentsDTO
    {
        return new GetCalendarDayAppointmentsDTO(
            date: $this->getDate(),
            staffId: $this->getStaffId(),
            status: $this->getStatus(),
            offset: $this->getOffset(),
            limit: $this->getLimit(),
            relations: ['client', 'service', 'staff'],
        );
    }
}
