<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\DTOs\Reporting\DateRangeDTO;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

final class ReportingRequest extends FormRequest
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
            'period' => ['nullable', 'string', 'in:today,this_week,last_week,this_month,last_month,custom'],
            'start_date' => ['nullable', 'date', 'required_if:period,custom'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_if:period,custom'],
            'include_comparison' => ['nullable', 'boolean'],
        ];
    }

    public function getPeriod(): string
    {
        return $this->validated('period') ?? 'this_month';
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

    public function shouldIncludeComparison(): bool
    {
        return (bool) ($this->validated('include_comparison') ?? true);
    }

    public function toDateRangeDTO(): DateRangeDTO
    {
        return match ($this->getPeriod()) {
            'today' => DateRangeDTO::today(),
            'this_week' => DateRangeDTO::thisWeek(),
            'last_week' => DateRangeDTO::lastWeek(),
            'this_month' => DateRangeDTO::thisMonth(),
            'last_month' => DateRangeDTO::lastMonth(),
            'custom' => DateRangeDTO::custom($this->getStartDate(), $this->getEndDate()),
            default => DateRangeDTO::thisMonth(),
        };
    }
}
