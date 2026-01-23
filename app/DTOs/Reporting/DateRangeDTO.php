<?php

declare(strict_types=1);

namespace App\DTOs\Reporting;

use Carbon\Carbon;

final readonly class DateRangeDTO
{
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
    ) {}

    public static function today(): self
    {
        return new self(
            Carbon::today()->startOfDay(),
            Carbon::today()->endOfDay(),
        );
    }

    public static function thisWeek(): self
    {
        return new self(
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        );
    }

    public static function lastWeek(): self
    {
        return new self(
            Carbon::now()->subWeek()->startOfWeek(),
            Carbon::now()->subWeek()->endOfWeek(),
        );
    }

    public static function thisMonth(): self
    {
        return new self(
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );
    }

    public static function lastMonth(): self
    {
        return new self(
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth(),
        );
    }

    public static function custom(Carbon $start, Carbon $end): self
    {
        return new self(
            $start->startOfDay(),
            $end->endOfDay(),
        );
    }

    public function getPreviousPeriod(): self
    {
        $days = $this->startDate->diffInDays($this->endDate);

        return new self(
            $this->startDate->copy()->subDays($days + 1),
            $this->endDate->copy()->subDays($days + 1),
        );
    }
}
