<?php

declare(strict_types=1);

namespace App\DTOs\Reporting;

final readonly class PeriodComparisonDTO
{
    public function __construct(
        public ReportMetricsDTO $previousPeriod,
        public float $revenueChange,
        public float $appointmentChange,
        public float $occupancyChange,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'previous_period' => $this->previousPeriod->toArray(),
            'changes' => [
                'revenue' => round($this->revenueChange, 2),
                'appointments' => round($this->appointmentChange, 2),
                'occupancy' => round($this->occupancyChange, 2),
            ],
        ];
    }
}
