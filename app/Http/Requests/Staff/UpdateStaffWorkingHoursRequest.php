<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateStaffWorkingHoursRequest extends FormRequest
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
            'working_hours' => ['required', 'array'],
            'working_hours.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'working_hours.*.start_time' => ['required', 'date_format:H:i'],
            'working_hours.*.end_time' => ['required', 'date_format:H:i', 'after:working_hours.*.start_time'],
            'working_hours.*.is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, array{day_of_week: int, start_time: string, end_time: string, is_active: bool}>
     */
    public function getWorkingHours(): array
    {
        $workingHours = $this->validated('working_hours');

        return array_map(static function (array $hours): array {
            return [
                'day_of_week' => (int) $hours['day_of_week'],
                'start_time' => $hours['start_time'],
                'end_time' => $hours['end_time'],
                'is_active' => (bool) ($hours['is_active'] ?? true),
            ];
        }, $workingHours);
    }
}
