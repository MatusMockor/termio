<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeOff;

use App\DTOs\TimeOff\UpdateTimeOffDTO;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTimeOffRequest extends FormRequest
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
            'staff_id' => ['nullable', 'exists:staff_profiles,id'],
            'date' => ['sometimes', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function getStaffId(): ?int
    {
        $staffId = $this->validated('staff_id');

        return $staffId !== null ? (int) $staffId : null;
    }

    public function getDate(): ?string
    {
        return $this->validated('date');
    }

    public function getStartTime(): ?string
    {
        return $this->validated('start_time');
    }

    public function getEndTime(): ?string
    {
        return $this->validated('end_time');
    }

    public function getReason(): ?string
    {
        return $this->validated('reason');
    }

    public function toDTO(): UpdateTimeOffDTO
    {
        return new UpdateTimeOffDTO(
            staffId: $this->getStaffId(),
            date: $this->getDate(),
            startTime: $this->getStartTime(),
            endTime: $this->getEndTime(),
            reason: $this->getReason(),
        );
    }
}
