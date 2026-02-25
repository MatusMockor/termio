<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointment;

use App\DTOs\Appointment\GetCalendarAppointmentsDTO;
use App\Enums\AppointmentStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

final class CalendarAppointmentsRequest extends FormRequest
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'staff_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'status' => ['nullable', Rule::in(AppointmentStatus::values())],
            'per_day' => ['nullable', 'integer', 'min:1', "max:{$maxPerDay}"],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $maxRangeDays = (int) config('appointments.calendar.max_range_days');
            $startDate = $this->validated('start_date');
            $endDate = $this->validated('end_date');

            if (! is_string($startDate) || ! is_string($endDate)) {
                return;
            }

            try {
                $parsedStartDate = Carbon::parse($startDate);
                $parsedEndDate = Carbon::parse($endDate);
            } catch (Throwable) {
                return;
            }

            if ($parsedStartDate->diffInDays($parsedEndDate) <= $maxRangeDays) {
                return;
            }

            $validator->errors()->add('end_date', "Date range cannot exceed {$maxRangeDays} days.");
        });
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

    public function getStatus(): ?AppointmentStatus
    {
        $status = $this->validated('status');

        if (! is_string($status)) {
            return null;
        }

        return AppointmentStatus::tryFrom($status);
    }

    public function getPerDay(): int
    {
        $perDay = $this->validated('per_day');

        if (! $perDay) {
            return (int) config('appointments.calendar.per_day.default');
        }

        return (int) $perDay;
    }

    public function toDTO(): GetCalendarAppointmentsDTO
    {
        return new GetCalendarAppointmentsDTO(
            startDate: $this->getStartDate(),
            endDate: $this->getEndDate(),
            staffId: $this->getStaffId(),
            status: $this->getStatus(),
            perDay: $this->getPerDay(),
            relations: ['client', 'service', 'staff'],
        );
    }
}
